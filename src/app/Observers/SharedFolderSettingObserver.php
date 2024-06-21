<?php

namespace App\Observers;

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
        $this->dispatchUpdateJob($folderSetting);
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
        $this->dispatchUpdateJob($folderSetting);
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
        $this->dispatchUpdateJob($folderSetting);
    }

    /**
     * Dispatch shared folder update job (if needed).
     *
     * @param \App\SharedFolderSetting $folderSetting Settings object
     */
    private function dispatchUpdateJob(SharedFolderSetting $folderSetting): void
    {
        if (
            (\config('app.with_ldap') && in_array($folderSetting->key, \App\Backends\LDAP::SHARED_FOLDER_SETTINGS))
            || (\config('app.with_imap') && in_array($folderSetting->key, \App\Backends\IMAP::SHARED_FOLDER_SETTINGS))
        ) {
            $props = [$folderSetting->key => $folderSetting->getOriginal('value')];
            \App\Jobs\SharedFolder\UpdateJob::dispatch($folderSetting->shared_folder_id, $props);
        }
    }
}
