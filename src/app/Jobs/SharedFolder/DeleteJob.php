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

        \App\Backends\LDAP::deleteSharedFolder($folder);

        $folder->status |= \App\SharedFolder::STATUS_DELETED;

        if ($folder->isLdapReady()) {
            $folder->status ^= \App\SharedFolder::STATUS_LDAP_READY;
        }

        if ($folder->isImapReady()) {
            $folder->status ^= \App\SharedFolder::STATUS_IMAP_READY;
        }

        $folder->save();
    }
}
