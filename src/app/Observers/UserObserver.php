<?php

namespace App\Observers;

use App\User;

class UserObserver
{
    /**
     * Handle the user "creating" event.
     *
     * Ensure that the user is created with a random, large integer.
     *
     * @param \App\User $user The user being created.
     *
     * @return void
     */
    public function creating(User $user)
    {
        $user->{$user->getKeyName()} = \App\Utils::uuidInt();

        \App\Jobs\ProcessUserCreate::dispatch($user);
    }

    /**
     * Handle the user "created" event.
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
    }

    /**
     * Handle the user "updated" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        //
    }

    /**
     * Handle the user "deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
    }

    public function deleting(User $user)
    {
        \App\Jobs\ProcessUserDelete::dispatch($user);
    }

    /**
     * Handle the user "restored" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    public function retrieving(User $user)
    {
        \App\Jobs\ProcessUserRead::dispatch($user);
    }

    public function updating(User $user)
    {
        \App\Jobs\ProcessUserUpdate::dispatch($user);
    }

    /**
     * Handle the user "force deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }
}
