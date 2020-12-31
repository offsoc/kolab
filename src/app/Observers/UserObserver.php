<?php

namespace App\Observers;

use App\Entitlement;
use App\Domain;
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
        if (!$user->id) {
            while (true) {
                $allegedly_unique = \App\Utils::uuidInt();
                if (!User::withTrashed()->find($allegedly_unique)) {
                    $user->{$user->getKeyName()} = $allegedly_unique;
                    break;
                }
            }
        }

        $user->email = \strtolower($user->email);

        // only users that are not imported get the benefit of the doubt.
        $user->status |= User::STATUS_NEW | User::STATUS_ACTIVE;

        // can't dispatch job here because it'll fail serialization
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
            'currency' => 'CHF',
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
        //
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
        $entitlements = [];

        foreach ($assignments as $entitlement) {
            if ($entitlement->entitleable_type == Domain::class) {
                $domains[] = $entitlement->entitleable_id;
            } elseif ($entitlement->entitleable_type == User::class && $entitlement->entitleable_id != $user->id) {
                $users[] = $entitlement->entitleable_id;
            } else {
                $entitlements[] = $entitlement->id;
            }
        }

        $users = array_unique($users);
        $domains = array_unique($domains);

        // Domains/users/entitlements need to be deleted one by one to make sure
        // events are fired and observers can do the proper cleanup.
        if (!empty($users)) {
            foreach (User::whereIn('id', $users)->get() as $_user) {
                $_user->delete();
            }
        }

        if (!empty($domains)) {
            foreach (Domain::whereIn('id', $domains)->get() as $_domain) {
                $_domain->delete();
            }
        }

        if (!empty($entitlements)) {
            Entitlement::whereIn('id', $entitlements)->delete();
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
            }
        }

        $users = array_unique($users);
        $domains = array_unique($domains);

        // Remove the user "direct" entitlements explicitely, if they belong to another
        // user's wallet they will not be removed by the wallets foreign key cascade
        Entitlement::withTrashed()
            ->where('entitleable_id', $user->id)
            ->where('entitleable_type', User::class)
            ->forceDelete();

        // Users need to be deleted one by one to make sure observers can do the proper cleanup.
        if (!empty($users)) {
            foreach (User::withTrashed()->whereIn('id', $users)->get() as $_user) {
                $_user->forceDelete();
            }
        }

        // Domains can be just removed
        if (!empty($domains)) {
            Domain::withTrashed()->whereIn('id', $domains)->forceDelete();
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
