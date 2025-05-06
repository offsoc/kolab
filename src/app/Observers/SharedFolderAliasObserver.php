<?php

namespace App\Observers;

use App\Domain;
use App\Jobs\SharedFolder\UpdateJob;
use App\SharedFolderAlias;
use App\Utils;

class SharedFolderAliasObserver
{
    /**
     * Handle the "creating" event on an alias
     *
     * @param SharedFolderAlias $alias The shared folder email alias
     */
    public function creating(SharedFolderAlias $alias): bool
    {
        $alias->alias = Utils::emailToLower($alias->alias);

        $domainName = explode('@', $alias->alias)[1];

        $domain = Domain::where('namespace', $domainName)->first();

        if (!$domain) {
            \Log::error("Failed creating alias {$alias->alias}. Domain does not exist.");
            return false;
        }

        if ($alias->sharedFolder) {
            if ($alias->sharedFolder->tenant_id != $domain->tenant_id) {
                \Log::error("Tenant for folder '{$alias->sharedFolder->email}' and domain '{$domainName}' differ.");
                return false;
            }
        }

        return true;
    }

    /**
     * Handle the shared folder alias "created" event.
     *
     * @param SharedFolderAlias $alias Shared folder email alias
     */
    public function created(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            UpdateJob::dispatch($alias->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder alias "updated" event.
     *
     * @param SharedFolderAlias $alias Shared folder email alias
     */
    public function updated(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            UpdateJob::dispatch($alias->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder alias "deleted" event.
     *
     * @param SharedFolderAlias $alias Shared folder email alias
     */
    public function deleted(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            UpdateJob::dispatch($alias->shared_folder_id);
        }
    }
}
