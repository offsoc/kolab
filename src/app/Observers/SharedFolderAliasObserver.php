<?php

namespace App\Observers;

use App\Domain;
use App\SharedFolder;
use App\SharedFolderAlias;

class SharedFolderAliasObserver
{
    /**
     * Handle the "creating" event on an alias
     *
     * @param \App\SharedFolderAlias $alias The shared folder email alias
     *
     * @return bool
     */
    public function creating(SharedFolderAlias $alias): bool
    {
        $alias->alias = \strtolower($alias->alias);

        $domainName = explode('@', $alias->alias)[1];

        $domain = Domain::where('namespace', $domainName)->first();

        if (!$domain) {
            \Log::error("Failed creating alias {$alias->alias}. Domain does not exist.");
            return false;
        }

        if ($alias->sharedFolder) {
            if ($alias->sharedFolder->tenant_id != $domain->tenant_id) {
                \Log::error("Reseller for folder '{$alias->sharedFolder->email}' and domain '{$domainName}' differ.");
                return false;
            }
        }

        return true;
    }

    /**
     * Handle the shared folder alias "created" event.
     *
     * @param \App\SharedFolderAlias $alias Shared folder email alias
     *
     * @return void
     */
    public function created(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($alias->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder alias "updated" event.
     *
     * @param \App\SharedFolderAlias $alias Shared folder email alias
     *
     * @return void
     */
    public function updated(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($alias->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder alias "deleted" event.
     *
     * @param \App\SharedFolderAlias $alias Shared folder email alias
     *
     * @return void
     */
    public function deleted(SharedFolderAlias $alias)
    {
        if ($alias->sharedFolder) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($alias->shared_folder_id);
        }
    }
}
