<?php

namespace App\Observers;

use App\Entitlement;
use App\Domain;
use App\Group;
use App\Transaction;
use App\User;
use App\Wallet;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the user is created with a random, large integer.
     *
     * @param \App\User $user The user being created.
     *
     * @return void
     */
    public function creating(User $user)
    {
        $user->email = \strtolower($user->email);

        // only users that are not imported get the benefit of the doubt.
        $user->status |= User::STATUS_NEW | User::STATUS_ACTIVE;
    }

    /**
     * Handle the "created" event.
     *
     * Ensures the user has at least one wallet.
     *
     * Should ensure some basic settings are available as well.
     *
     * @param \App\User $user The user created.
     *
     * @return void
     */
    public function created(User $user)
    {
        $settings = [
            'country' => \App\Utils::countryForRequest(),
            'currency' => \config('app.currency'),
            /*
            'first_name' => '',
            'last_name' => '',
            'billing_address' => '',
            'organization' => '',
            'phone' => '',
            'external_email' => '',
            */
        ];

        foreach ($settings as $key => $value) {
            $settings[$key] = [
                'key' => $key,
                'value' => $value,
                'user_id' => $user->id,
            ];
        }

        // Note: Don't use setSettings() here to bypass UserSetting observers
        // Note: This is a single multi-insert query
        $user->settings()->insert(array_values($settings));

        $user->wallets()->create();

        // Create user record in LDAP, then check if the account is created in IMAP
        $chain = [
            new \App\Jobs\User\VerifyJob($user->id),
        ];

        \App\Jobs\User\CreateJob::withChain($chain)->dispatch($user->id);

        if (\App\Tenant::getConfig($user->tenant_id, 'pgp.enable')) {
            \App\Jobs\PGP\KeyCreateJob::dispatch($user->id, $user->email);
        }
    }

    /**
     * Handle the "deleted" event.
     *
     * @param \App\User $user The user deleted.
     *
     * @return void
     */
    public function deleted(User $user)
    {
        // Remove the user from existing groups
        $wallet = $user->wallet();
        if ($wallet && $wallet->owner) {
            $wallet->owner->groups()->each(function ($group) use ($user) {
                if (in_array($user->email, $group->members)) {
                    $group->members = array_diff($group->members, [$user->email]);
                    $group->save();
                }
            });
        }

        // Debit the reseller's wallet with the user negative balance
        $balance = 0;
        foreach ($user->wallets as $wallet) {
            // Note: here we assume all user wallets are using the same currency.
            //       It might get changed in the future
            $balance += $wallet->balance;
        }

        if ($balance < 0 && $user->tenant && ($wallet = $user->tenant->wallet())) {
            $wallet->debit($balance * -1, "Deleted user {$user->email}");
        }
    }

    /**
     * Handle the "deleting" event.
     *
     * @param User $user The user that is being deleted.
     *
     * @return void
     */
    public function deleting(User $user)
    {
        if ($user->isForceDeleting()) {
            $this->forceDeleting($user);
            return;
        }

        // TODO: Especially in tests we're doing delete() on a already deleted user.
        //       Should we escape here - for performance reasons?
        // TODO: I think all of this should use database transactions

        // Entitlements do not have referential integrity on the entitled object, so this is our
        // way of doing an onDelete('cascade') without the foreign key.
        $entitlements = Entitlement::where('entitleable_id', $user->id)
            ->where('entitleable_type', User::class)->get();

        foreach ($entitlements as $entitlement) {
            $entitlement->delete();
        }

        // Remove owned users/domains
        $wallets = $user->wallets()->pluck('id')->all();
        $assignments = Entitlement::whereIn('wallet_id', $wallets)->get();
        $users = [];
        $domains = [];
        $groups = [];
        $entitlements = [];

        foreach ($assignments as $entitlement) {
            if ($entitlement->entitleable_type == Domain::class) {
                $domains[] = $entitlement->entitleable_id;
            } elseif ($entitlement->entitleable_type == User::class && $entitlement->entitleable_id != $user->id) {
                $users[] = $entitlement->entitleable_id;
            } elseif ($entitlement->entitleable_type == Group::class) {
                $groups[] = $entitlement->entitleable_id;
            } else {
                $entitlements[] = $entitlement;
            }
        }

        // Domains/users/entitlements need to be deleted one by one to make sure
        // events are fired and observers can do the proper cleanup.
        if (!empty($users)) {
            foreach (User::whereIn('id', array_unique($users))->get() as $_user) {
                $_user->delete();
            }
        }

        if (!empty($domains)) {
            foreach (Domain::whereIn('id', array_unique($domains))->get() as $_domain) {
                $_domain->delete();
            }
        }

        if (!empty($groups)) {
            foreach (Group::whereIn('id', array_unique($groups))->get() as $_group) {
                $_group->delete();
            }
        }

        foreach ($entitlements as $entitlement) {
            $entitlement->delete();
        }

        // FIXME: What do we do with user wallets?

        \App\Jobs\User\DeleteJob::dispatch($user->id);
    }

    /**
     * Handle the "deleting" event on forceDelete() call.
     *
     * @param User $user The user that is being deleted.
     *
     * @return void
     */
    public function forceDeleting(User $user)
    {
        // TODO: We assume that at this moment all belongings are already soft-deleted.

        // Remove owned users/domains
        $wallets = $user->wallets()->pluck('id')->all();
        $assignments = Entitlement::withTrashed()->whereIn('wallet_id', $wallets)->get();
        $entitlements = [];
        $domains = [];
        $groups = [];
        $users = [];

        foreach ($assignments as $entitlement) {
            $entitlements[] = $entitlement->id;

            if ($entitlement->entitleable_type == Domain::class) {
                $domains[] = $entitlement->entitleable_id;
            } elseif (
                $entitlement->entitleable_type == User::class
                && $entitlement->entitleable_id != $user->id
            ) {
                $users[] = $entitlement->entitleable_id;
            } elseif ($entitlement->entitleable_type == Group::class) {
                $groups[] = $entitlement->entitleable_id;
            }
        }

        // Remove the user "direct" entitlements explicitely, if they belong to another
        // user's wallet they will not be removed by the wallets foreign key cascade
        Entitlement::withTrashed()
            ->where('entitleable_id', $user->id)
            ->where('entitleable_type', User::class)
            ->forceDelete();

        // Users need to be deleted one by one to make sure observers can do the proper cleanup.
        if (!empty($users)) {
            foreach (User::withTrashed()->whereIn('id', array_unique($users))->get() as $_user) {
                $_user->forceDelete();
            }
        }

        // Domains can be just removed
        if (!empty($domains)) {
            Domain::withTrashed()->whereIn('id', array_unique($domains))->forceDelete();
        }

        // Groups can be just removed
        if (!empty($groups)) {
            Group::withTrashed()->whereIn('id', array_unique($groups))->forceDelete();
        }

        // Remove transactions, they also have no foreign key constraint
        Transaction::where('object_type', Entitlement::class)
            ->whereIn('object_id', $entitlements)
            ->delete();

        Transaction::where('object_type', Wallet::class)
            ->whereIn('object_id', $wallets)
            ->delete();
    }

    /**
     * Handle the user "restoring" event.
     *
     * @param \App\User $user The user
     *
     * @return void
     */
    public function restoring(User $user)
    {
        // Make sure it's not DELETED/LDAP_READY/IMAP_READY/SUSPENDED anymore
        if ($user->isDeleted()) {
            $user->status ^= User::STATUS_DELETED;
        }
        if ($user->isLdapReady()) {
            $user->status ^= User::STATUS_LDAP_READY;
        }
        if ($user->isImapReady()) {
            $user->status ^= User::STATUS_IMAP_READY;
        }
        if ($user->isSuspended()) {
            $user->status ^= User::STATUS_SUSPENDED;
        }

        $user->status |= User::STATUS_ACTIVE;

        // Note: $user->save() is invoked between 'restoring' and 'restored' events
    }

    /**
     * Handle the user "restored" event.
     *
     * @param \App\User $user The user
     *
     * @return void
     */
    public function restored(User $user)
    {
        $wallets = $user->wallets()->pluck('id')->all();

        // Restore user entitlements
        // We'll restore only these that were deleted last. So, first we get
        // the maximum deleted_at timestamp and then use it to select
        // entitlements for restore
        $deleted_at = \App\Entitlement::withTrashed()
            ->where('entitleable_id', $user->id)
            ->where('entitleable_type', User::class)
            ->max('deleted_at');

        if ($deleted_at) {
            $threshold = (new \Carbon\Carbon($deleted_at))->subMinute();

            // We need at least the user domain so it can be created in ldap.
            // FIXME: What if the domain is owned by someone else?
            $domain = $user->domain();
            if ($domain->trashed() && !$domain->isPublic()) {
                // Note: Domain entitlements will be restored by the DomainObserver
                $domain->restore();
            }

            // Restore user entitlements
            \App\Entitlement::withTrashed()
                ->where('entitleable_id', $user->id)
                ->where('entitleable_type', User::class)
                ->where('deleted_at', '>=', $threshold)
                ->update(['updated_at' => now(), 'deleted_at' => null]);

            // Note: We're assuming that cost of entitlements was correct
            // on user deletion, so we don't have to re-calculate it again.
        }

        // FIXME: Should we reset user aliases? or re-validate them in any way?

        // Create user record in LDAP, then run the verification process
        $chain = [
            new \App\Jobs\User\VerifyJob($user->id),
        ];

        \App\Jobs\User\CreateJob::withChain($chain)->dispatch($user->id);
    }

    /**
     * Handle the "retrieving" event.
     *
     * @param User $user The user that is being retrieved.
     *
     * @todo This is useful for audit.
     *
     * @return void
     */
    public function retrieving(User $user)
    {
        // TODO   \App\Jobs\User\ReadJob::dispatch($user->id);
    }

    /**
     * Handle the "updating" event.
     *
     * @param User $user The user that is being updated.
     *
     * @return void
     */
    public function updating(User $user)
    {
        \App\Jobs\User\UpdateJob::dispatch($user->id);
    }
}
