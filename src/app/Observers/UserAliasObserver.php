<?php

namespace App\Observers;

use App\Domain;
use App\Jobs\PGP\KeyCreateJob;
use App\Jobs\PGP\KeyDeleteJob;
use App\Jobs\User\UpdateJob;
use App\Tenant;
use App\User;
use App\UserAlias;

class UserAliasObserver
{
    /**
     * Handle the "creating" event on an alias
     *
     * @param UserAlias $alias The user email alias
     */
    public function creating(UserAlias $alias): bool
    {
        $alias->alias = \strtolower($alias->alias);

        [$login, $domain] = explode('@', $alias->alias);

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
     * @param UserAlias $alias User email alias
     */
    public function created(UserAlias $alias)
    {
        if ($alias->user) {
            UpdateJob::dispatch($alias->user_id);

            if (Tenant::getConfig($alias->user->tenant_id, 'pgp.enable')) {
                KeyCreateJob::dispatch($alias->user_id, $alias->alias);
            }
        }
    }

    /**
     * Handle the user alias "updated" event.
     *
     * @param UserAlias $alias User email alias
     */
    public function updated(UserAlias $alias)
    {
        if ($alias->user) {
            UpdateJob::dispatch($alias->user_id);
        }
    }

    /**
     * Handle the user alias "deleted" event.
     *
     * @param UserAlias $alias User email alias
     */
    public function deleted(UserAlias $alias)
    {
        if ($alias->user) {
            UpdateJob::dispatch($alias->user_id);

            if (Tenant::getConfig($alias->user->tenant_id, 'pgp.enable')) {
                KeyDeleteJob::dispatch($alias->user_id, $alias->alias);
            }
        }
    }
}
