<?php

namespace App\Jobs\SharedFolder;

use App\Jobs\SharedFolderJob;

class VerifyJob extends SharedFolderJob
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

        // the user has a mailbox (or is marked as such)
        if ($folder->isImapReady()) {
            $this->fail(new \Exception("Shared folder {$this->folderId} is already verified."));
            return;
        }

        $folderName = $folder->getSetting('folder');

        if (\App\Backends\IMAP::verifySharedFolder($folderName)) {
            $folder->status |= \App\SharedFolder::STATUS_IMAP_READY;
            $folder->status |= \App\SharedFolder::STATUS_ACTIVE;
            $folder->save();
        }
    }
}
