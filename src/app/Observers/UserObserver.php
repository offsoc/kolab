<?php

namespace App\Observers;

use App\Delegation;
use App\Group;
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

        $user->status |= User::STATUS_NEW;
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
        if ($user->role == \App\User::ROLE_SERVICE) {
            return;
        }

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

        // Create user record in the backend (LDAP and IMAP)
        \App\Jobs\User\CreateJob::dispatch($user->id);

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
        if ($user->role == \App\User::ROLE_SERVICE) {
            return;
        }

        if (!$user->isForceDeleting()) {
            \App\Jobs\User\DeleteJob::dispatch($user->id);

            if (\App\Tenant::getConfig($user->tenant_id, 'pgp.enable')) {
                \App\Jobs\PGP\KeyDeleteJob::dispatch($user->id, $user->email);
            }
        }

        // Remove the user from existing groups
        $wallet = $user->wallet();
        if ($wallet && $wallet->owner) {
            $wallet->owner->groups()->each(function ($group) use ($user) {
                /** @var Group $group */
                if (in_array($user->email, $group->members)) {
                    $group->members = array_diff($group->members, [$user->email]);
                    $group->save();
                }
            });
        }

        // Remove delegation relations
        $ids = Delegation::where('user_id', $user->id)->orWhere('delegatee_id', $user->id)->get()
            ->map(function ($delegation) use ($user) {
                $delegator = $delegation->user_id == $user->id
                    ? $user : $delegation->user()->withTrashed()->first();
                $delegatee = $delegation->delegatee_id == $user->id
                    ? $user : $delegation->delegatee()->withTrashed()->first();

                \App\Jobs\User\Delegation\DeleteJob::dispatch($delegator->email, $delegatee->email);

                return $delegation->id;
            })
            ->all();

        if (!empty($ids)) {
            Delegation::whereIn('id', $ids)->delete();
        }

        // TODO: Remove Permission records for the user
        // TODO: Remove file permissions for the user
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
        if ($user->role == \App\User::ROLE_SERVICE) {
            return;
        }

        // Remove owned users/domains/groups/resources/etc
        self::removeRelatedObjects($user, $user->isForceDeleting());
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
        // Reset the status
        $user->status = User::STATUS_NEW;

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
        if ($user->role == \App\User::ROLE_SERVICE) {
            return;
        }

        // We need at least the user domain so it can be created in ldap.
        // FIXME: What if the domain is owned by someone else?
        $domain = $user->domain();
        if ($domain->trashed() && !$domain->isPublic()) {
            // Note: Domain entitlements will be restored by the DomainObserver
            $domain->restore();
        }

        // FIXME: Should we reset user aliases? or re-validate them in any way?

        // Create user record in the backend (LDAP and IMAP)
        \App\Jobs\User\CreateJob::dispatch($user->id);
    }

    /**
     * Handle the "updated" event.
     *
     * @param \App\User $user The user that is being updated.
     *
     * @return void
     */
    public function updated(User $user)
    {
        if ($user->role == \App\User::ROLE_SERVICE) {
            return;
        }

        if (!$user->trashed()) {
            \App\Jobs\User\UpdateJob::dispatch($user->id);
        }

        $oldStatus = $user->getOriginal('status');
        $newStatus = $user->status;

        if (($oldStatus & User::STATUS_DEGRADED) !== ($newStatus & User::STATUS_DEGRADED)) {
            $wallets = [];
            $isDegraded = $user->isDegraded();

            // Charge all entitlements as if they were being deleted,
            // but don't delete them. Just debit the wallet and update
            // entitlements' updated_at timestamp. On un-degrade we still
            // update updated_at, but with no debit (the cost is 0 on a degraded account).
            foreach ($user->wallets as $wallet) {
                $wallet->updateEntitlements($isDegraded);

                // Remember time of the degradation for sending periodic reminders
                // and reset it on un-degradation
                $val = $isDegraded ? \Carbon\Carbon::now()->toDateTimeString() : null;
                $wallet->setSetting('degraded_last_reminder', $val);

                $wallets[] = $wallet->id;
            }

            // (Un-)degrade users by invoking an update job.
            // LDAP backend will read the wallet owner's degraded status and
            // set LDAP attributes accordingly.
            // We do not change their status as their wallets have its own state
            \App\Entitlement::whereIn('wallet_id', $wallets)
                ->where('entitleable_id', '!=', $user->id)
                ->where('entitleable_type', User::class)
                ->pluck('entitleable_id')
                ->unique()
                ->each(function ($user_id) {
                    \App\Jobs\User\UpdateJob::dispatch($user_id);
                });
        }

        // Save the old password in the password history
        $oldPassword = $user->getOriginal('password');
        if ($oldPassword && $user->password != $oldPassword) {
            self::saveOldPassword($user, $oldPassword);
        }
    }

    /**
     * Remove entities related to the user (in user's wallets), entitlements, transactions, etc.
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

                if (!$entitlement->entitleable) {
                    return;
                }

                // Objects need to be deleted one by one to make sure observers can do the proper cleanup
                if ($force) {
                    $entitlement->entitleable->forceDelete();
                } elseif (!$entitlement->entitleable->trashed()) {
                    $entitlement->entitleable->delete();
                }
            });

        if ($force) {
            // Remove "wallet" transactions, they have no foreign key constraint
            \App\Transaction::where('object_type', Wallet::class)
                ->whereIn('object_id', $wallets)
                ->delete();

            // Remove EventLog records
            \App\EventLog::where('object_id', $user->id)->where('object_type', User::class)->delete();
        }

        // regardless of force delete, we're always purging whitelists... just in case
        \App\Policy\RateLimit\Whitelist::where(
            [
                'whitelistable_id' => $user->id,
                'whitelistable_type' => User::class
            ]
        )->delete();
    }

    /**
     * Store the old password in user password history. Make sure
     * we do not store more passwords than we need in the history.
     *
     * @param \App\User $user     The user
     * @param string    $password The old password
     */
    private static function saveOldPassword(User $user, string $password): void
    {
        // Remember the timestamp of the last password change and unset the last warning date
        $user->setSettings([
                'password_expiration_warning' => null,
                // Note: We could get this from user_passwords table, but only if the policy
                // enables storing of old passwords there.
                'password_update' => now()->format('Y-m-d H:i:s'),
        ]);

        // Note: All this is kinda heavy and complicated because we don't want to store
        // more old passwords than we need. However, except the complication/performance,
        // there's one issue with it. E.g. the policy changes from 2 to 4, and we already
        // removed the old passwords that were excessive before, but not now.

        // Get the account password policy
        $policy = new \App\Rules\Password($user->walletOwner());
        $rules = $policy->rules();

        // Password history disabled?
        if (empty($rules['last']) || $rules['last']['param'] < 2) {
            return;
        }

        // Store the old password
        $user->passwords()->create(['password' => $password]);

        // Remove passwords that we don't need anymore
        $limit = $rules['last']['param'] - 1;
        $ids = $user->passwords()->latest()->limit($limit)->pluck('id')->all();

        if (count($ids) >= $limit) {
            $user->passwords()->where('id', '<', $ids[count($ids) - 1])->delete();
        }
    }
}
