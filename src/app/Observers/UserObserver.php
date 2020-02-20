<?php

namespace App\Observers;

use App\User;

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
        while (true) {
            $allegedly_unique = \App\Utils::uuidInt();
            if (!User::find($allegedly_unique)) {
                $user->{$user->getKeyName()} = $allegedly_unique;
                break;
            }
        }

        $user->status |= User::STATUS_NEW;

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
        // FIXME: Actual proper settings
        $user->setSettings(
            [
                'country' => 'CH',
                'currency' => 'CHF',
                'first_name' => '',
                'last_name' => '',
                'billing_address' => '',
                'organization' => ''
            ]
        );

        $user->wallets()->create();

        // Create user record in LDAP, then check if the account is created in IMAP
        $chain = [
            new \App\Jobs\UserVerify($user),
        ];

        \App\Jobs\UserCreate::withChain($chain)->dispatch($user);
    }

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
        // Entitlements do not have referential integrity on the entitled object, so this is our
        // way of doing an onDelete('cascade') without the foreign key.
        $entitlements = \App\Entitlement::where('entitleable_id', $user->id)
            ->where('entitleable_type', \App\User::class)->get();

        foreach ($entitlements as $entitlement) {
            $entitlement->delete();
        }

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
