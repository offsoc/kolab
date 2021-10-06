<?php

namespace App\Observers;

use App\Domain;
use App\Tenant;
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

        if ($alias->user) {
            if ($alias->user->tenant_id != $domain->tenant_id) {
                \Log::error("Reseller for user '{$alias->user->email}' and domain '{$domain->namespace}' differ.");
                return false;
            }
        }

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

            if (Tenant::getConfig($alias->user->tenant_id, 'pgp.enable')) {
                \App\Jobs\PGP\KeyCreateJob::dispatch($alias->user_id, $alias->alias);
            }
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

            if (Tenant::getConfig($alias->user->tenant_id, 'pgp.enable')) {
                \App\Jobs\PGP\KeyDeleteJob::dispatch($alias->user_id, $alias->alias);
            }
        }
    }
}
