<?php

namespace App\Jobs\SharedFolder;

use App\Jobs\SharedFolderJob;

class DeleteJob extends SharedFolderJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $folder = $this->getSharedFolder();

        if (!$folder) {
            return;
        }

        // sanity checks
        if ($folder->isDeleted()) {
            $this->fail(new \Exception("Shared folder {$this->folderId} is already marked as deleted."));
            return;
        }

        if (\config('app.with_ldap') && $folder->isLdapReady()) {
            \App\Backends\LDAP::deleteSharedFolder($folder);

            $folder->status ^= \App\SharedFolder::STATUS_LDAP_READY;
            // Already save in case of exception below
            $folder->save();
        }

        if ($folder->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!\App\Backends\IMAP::deleteSharedFolder($folder)) {
                    throw new \Exception("Failed to delete mailbox for shared folder {$this->folderId}.");
                }
            }

            $folder->status ^= \App\SharedFolder::STATUS_IMAP_READY;
        }

        $folder->status |= \App\SharedFolder::STATUS_DELETED;
        $folder->save();
    }
}
