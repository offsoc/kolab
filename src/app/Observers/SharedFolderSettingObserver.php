<?php

namespace App\Observers;

use App\Jobs\SharedFolder\UpdateJob;
use App\SharedFolderSetting;

class SharedFolderSettingObserver
{
    /**
     * Handle the shared folder setting "created" event.
     *
     * @param SharedFolderSetting $folderSetting Settings object
     */
    public function created(SharedFolderSetting $folderSetting)
    {
        $this->dispatchUpdateJob($folderSetting);
    }

    /**
     * Handle the shared folder setting "updated" event.
     *
     * @param SharedFolderSetting $folderSetting Settings object
     */
    public function updated(SharedFolderSetting $folderSetting)
    {
        $this->dispatchUpdateJob($folderSetting);
    }

    /**
     * Handle the shared folder setting "deleted" event.
     *
     * @param SharedFolderSetting $folderSetting Settings object
     */
    public function deleted(SharedFolderSetting $folderSetting)
    {
        $this->dispatchUpdateJob($folderSetting);
    }

    /**
     * Dispatch shared folder update job (if needed).
     *
     * @param SharedFolderSetting $folderSetting Settings object
     */
    private function dispatchUpdateJob(SharedFolderSetting $folderSetting): void
    {
        if ($folderSetting->isBackendSetting()) {
            $props = [$folderSetting->key => $folderSetting->getOriginal('value')];
            UpdateJob::dispatch($folderSetting->shared_folder_id, $props);
        }
    }
}
