<?php

namespace App\Observers;

use App\User;
use App\Wallet;

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
        // Remove owned users/domains/groups/resources/etc
        self::removeRelatedObjects($user, $user->isForceDeleting());

        // TODO: Especially in tests we're doing delete() on a already deleted user.
        //       Should we escape here - for performance reasons?

        if (!$user->isForceDeleting()) {
            \App\Jobs\User\DeleteJob::dispatch($user->id);

            if (\App\Tenant::getConfig($user->tenant_id, 'pgp.enable')) {
                \App\Jobs\PGP\KeyDeleteJob::dispatch($user->id, $user->email);
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
        // We need at least the user domain so it can be created in ldap.
        // FIXME: What if the domain is owned by someone else?
        $domain = $user->domain();
        if ($domain->trashed() && !$domain->isPublic()) {
            // Note: Domain entitlements will be restored by the DomainObserver
            $domain->restore();
        }

        // FIXME: Should we reset user aliases? or re-validate them in any way?

        // Create user record in LDAP, then run the verification process
        $chain = [
            new \App\Jobs\User\VerifyJob($user->id),
        ];

        \App\Jobs\User\CreateJob::withChain($chain)->dispatch($user->id);
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

    /**
     * Remove entitleables/transactions related to the user (in user's wallets)
     *
     * @param \App\User $user  The user
     * @param bool      $force Force-delete mode
     */
    private static function removeRelatedObjects(User $user, $force = false): void
    {
        $wallets = $user->wallets->pluck('id')->all();

        \App\Entitlement::withTrashed()
            ->select('entitleable_id', 'entitleable_type')
            ->distinct()
            ->whereIn('wallet_id', $wallets)
            ->get()
            ->each(function ($entitlement) use ($user, $force) {
                // Skip the current user (infinite recursion loop)
                if ($entitlement->entitleable_type == User::class && $entitlement->entitleable_id == $user->id) {
                    return;
                }

                // Objects need to be deleted one by one to make sure observers can do the proper cleanup
                if ($entitlement->entitleable) {
                    if ($force) {
                        $entitlement->entitleable->forceDelete();
                    } elseif (!$entitlement->entitleable->trashed()) {
                        $entitlement->entitleable->delete();
                    }
                }
            });

        if ($force) {
            // Remove "wallet" transactions, they have no foreign key constraint
            \App\Transaction::where('object_type', Wallet::class)
                ->whereIn('object_id', $wallets)
                ->delete();
        }
    }
}
