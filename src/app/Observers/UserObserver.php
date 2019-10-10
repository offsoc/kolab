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

        \App\Jobs\ProcessUserCreate::dispatch($user);
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
        \App\Jobs\ProcessUserDelete::dispatch($user);
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
        \App\Jobs\ProcessUserRead::dispatch($user);
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
        \App\Jobs\ProcessUserUpdate::dispatch($user);
    }
}
