<?php

namespace App\Observers;

use App\Backends\LDAP;
use App\SharedFolderSetting;

class SharedFolderSettingObserver
{
    /**
     * Handle the shared folder setting "created" event.
     *
     * @param \App\SharedFolderSetting $folderSetting Settings object
     *
     * @return void
     */
    public function created(SharedFolderSetting $folderSetting)
    {
        if (in_array($folderSetting->key, LDAP::SHARED_FOLDER_SETTINGS)) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($folderSetting->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder setting "updated" event.
     *
     * @param \App\SharedFolderSetting $folderSetting Settings object
     *
     * @return void
     */
    public function updated(SharedFolderSetting $folderSetting)
    {
        if (in_array($folderSetting->key, LDAP::SHARED_FOLDER_SETTINGS)) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($folderSetting->shared_folder_id);
        }
    }

    /**
     * Handle the shared folder setting "deleted" event.
     *
     * @param \App\SharedFolderSetting $folderSetting Settings object
     *
     * @return void
     */
    public function deleted(SharedFolderSetting $folderSetting)
    {
        if (in_array($folderSetting->key, LDAP::SHARED_FOLDER_SETTINGS)) {
            \App\Jobs\SharedFolder\UpdateJob::dispatch($folderSetting->shared_folder_id);
        }
    }
}
