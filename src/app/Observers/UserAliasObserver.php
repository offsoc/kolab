<?php

namespace App\Observers;

use App\User;
use App\UserAlias;

class UserAliasObserver
{
    /**
     * Handle the "creating" event on an alias
     *
     * Ensures that there's no user with specified email.
     *
     * @param \App\UserAlias $alias The user email alias
     *
     * @return bool|null
     */
    public function creating(UserAlias $alias)
    {
        $alias->alias = \strtolower($alias->alias);

        if (User::where('email', $alias->alias)->first()) {
            \Log::error("Failed creating alias {$alias->alias}. User exists.");
            return false;
        }
    }

    /**
     * Handle the user alias "created" event.
     *
     * @param \App\UserAlias $alias User email alias
     *
     * @return void
     */
    public function created(UserAlias $alias)
    {
        \App\Jobs\UserUpdate::dispatch($alias->user);
    }

    /**
     * Handle the user setting "updated" event.
     *
     * @param \App\UserAlias $alias User email alias
     *
     * @return void
     */
    public function updated(UserAlias $alias)
    {
        \App\Jobs\UserUpdate::dispatch($alias->user);
    }

    /**
     * Handle the user setting "deleted" event.
     *
     * @param \App\UserAlias $alias User email alias
     *
     * @return void
     */
    public function deleted(UserAlias $alias)
    {
        \App\Jobs\UserUpdate::dispatch($alias->user);
    }

    /**
     * Handle the user alias "restored" event.
     *
     * @param \App\UserAlias $alias User email alias
     *
     * @return void
     */
    public function restored(UserAlias $alias)
    {
        // not used
    }

    /**
     * Handle the user alias "force deleted" event.
     *
     * @param \App\UserAlias $alias User email alias
     *
     * @return void
     */
    public function forceDeleted(UserAlias $alias)
    {
        // not used
    }
}
