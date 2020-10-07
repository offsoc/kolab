<?php

namespace App\Observers;

use App\Domain;
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
     * @return bool
     */
    public function creating(UserAlias $alias): bool
    {
        $alias->alias = \strtolower($alias->alias);

        list($login, $domain) = explode('@', $alias->alias);

        $domain = Domain::where('namespace', $domain)->first();

        if (!$domain) {
            \Log::error("Failed creating alias {$alias->alias}. Domain does not exist.");
            return false;
        }
/*
        if ($exists = User::emailExists($alias->alias, true, $alias_exists, !$domain->isPublic())) {
            if (!$alias_exists) {
                \Log::error("Failed creating alias {$alias->alias}. Email address exists.");
                return false;
            }

            if ($domain->isPublic()) {
                \Log::error("Failed creating alias {$alias->alias}. Alias exists in public domain.");
                return false;
            }

            if ($exists->wallet()->user_id != $alias->user->wallet()->user_id) {
                \Log::error("Failed creating alias {$alias->alias}. Alias exists in another account.");
                return false;
            }
        }
*/
        return true;
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
        if ($alias->user) {
            \App\Jobs\User\UpdateJob::dispatch($alias->user_id);
        }
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
        if ($alias->user) {
            \App\Jobs\User\UpdateJob::dispatch($alias->user_id);
        }
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
        if ($alias->user) {
            \App\Jobs\User\UpdateJob::dispatch($alias->user_id);
        }
    }
}
