<?php

namespace App\Observers;

use App\Entitlement;
use App\Domain;
use App\User;
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
                if (!User::find($allegedly_unique)) {
                    $user->{$user->getKeyName()} = $allegedly_unique;
                    break;
                }
            }
        }

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
            'country' => 'CH',
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
            new \App\Jobs\UserVerify($user),
        ];

        \App\Jobs\UserCreate::withChain($chain)->dispatch($user);
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

        // Note: Domains/users need to be deleted one by one to make sure
        //       events are fired and observers can do the proper cleanup.
        //       Entitlements have no delete event handlers as for now.
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

        \App\Jobs\UserDelete::dispatch($user->id);
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
        // TODO   \App\Jobs\UserRead::dispatch($user);
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
        \App\Jobs\UserUpdate::dispatch($user);
    }
}
