<?php

namespace App\Observers;

use App\Jobs\SharedFolder\CreateJob;
use App\Jobs\SharedFolder\DeleteJob;
use App\Jobs\SharedFolder\UpdateJob;
use App\SharedFolder;

class SharedFolderObserver
{
    /**
     * Handle the shared folder "creating" event.
     *
     * @param SharedFolder $folder The folder
     */
    public function creating(SharedFolder $folder): void
    {
        if (empty($folder->type)) {
            $folder->type = 'mail';
        }

        $folder->status |= SharedFolder::STATUS_NEW;
    }

    /**
     * Handle the shared folder "created" event.
     *
     * @param SharedFolder $folder The folder
     */
    public function created(SharedFolder $folder)
    {
        $domainName = explode('@', $folder->email, 2)[1];

        $settings = [
            'folder' => "shared/{$folder->name}@{$domainName}",
        ];

        foreach ($settings as $key => $value) {
            $settings[$key] = [
                'key' => $key,
                'value' => $value,
                'shared_folder_id' => $folder->id,
            ];
        }

        // Note: Don't use setSettings() here to bypass SharedFolderSetting observers
        // Note: This is a single multi-insert query
        $folder->settings()->insert(array_values($settings));

        // Create the shared folder in the backend (LDAP and IMAP)
        CreateJob::dispatch($folder->id);
    }

    /**
     * Handle the shared folder "deleted" event.
     *
     * @param SharedFolder $folder The folder
     */
    public function deleted(SharedFolder $folder)
    {
        if ($folder->isForceDeleting()) {
            return;
        }

        DeleteJob::dispatch($folder->id);
    }

    /**
     * Handle the shared folder "updated" event.
     *
     * @param SharedFolder $folder The folder
     */
    public function updated(SharedFolder $folder)
    {
        if (!$folder->trashed()) {
            UpdateJob::dispatch($folder->id);
        }

        // Update the folder property if name changed
        if ($folder->name != $folder->getOriginal('name')) {
            $domainName = explode('@', $folder->email, 2)[1];
            $folderName = "shared/{$folder->name}@{$domainName}";

            // Note: This does not invoke SharedFolderSetting observer events, good.
            $folder->settings()->where('key', 'folder')->update(['value' => $folderName]);
        }
    }
}
