<?php

namespace App\Observers;

use App\SharedFolder;

class SharedFolderObserver
{
    /**
     * Handle the shared folder "creating" event.
     *
     * @param \App\SharedFolder $folder The folder
     *
     * @return void
     */
    public function creating(SharedFolder $folder): void
    {
        if (empty($folder->type)) {
            $folder->type = 'mail';
        }

        if (empty($folder->email)) {
            if (!isset($folder->domain)) {
                throw new \Exception("Missing 'domain' property for a new shared folder");
            }

            $domainName = \strtolower($folder->domain);

            $folder->email = "{$folder->type}-{$folder->id}@{$domainName}";
        } else {
            $folder->email = \strtolower($folder->email);
        }

        $folder->status |= SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE;
    }

    /**
     * Handle the shared folder "created" event.
     *
     * @param \App\SharedFolder $folder The folder
     *
     * @return void
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

        // Create folder record in LDAP, then check if it is created in IMAP
        $chain = [
            new \App\Jobs\SharedFolder\VerifyJob($folder->id),
        ];

        \App\Jobs\SharedFolder\CreateJob::withChain($chain)->dispatch($folder->id);
    }

    /**
     * Handle the shared folder "deleted" event.
     *
     * @param \App\SharedFolder $folder The folder
     *
     * @return void
     */
    public function deleted(SharedFolder $folder)
    {
        if ($folder->isForceDeleting()) {
            return;
        }

        \App\Jobs\SharedFolder\DeleteJob::dispatch($folder->id);
    }

    /**
     * Handle the shared folder "updated" event.
     *
     * @param \App\SharedFolder $folder The folder
     *
     * @return void
     */
    public function updated(SharedFolder $folder)
    {
        \App\Jobs\SharedFolder\UpdateJob::dispatch($folder->id);

        // Update the folder property if name changed
        if ($folder->name != $folder->getOriginal('name')) {
            $domainName = explode('@', $folder->email, 2)[1];
            $folderName = "shared/{$folder->name}@{$domainName}";

            // Note: This does not invoke SharedFolderSetting observer events, good.
            $folder->settings()->where('key', 'folder')->update(['value' => $folderName]);
        }
    }
}
