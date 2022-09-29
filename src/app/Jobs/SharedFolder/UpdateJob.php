<?php

namespace App\Jobs\SharedFolder;

use App\Jobs\SharedFolderJob;

class UpdateJob extends SharedFolderJob
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

        // Cancel the update if the folder is deleted
        if ($folder->isDeleted()) {
            $this->delete();
            return;
        }

        if (\config('app.with_ldap') && $folder->isLdapReady()) {
            \App\Backends\LDAP::updateSharedFolder($folder);
        }

        if (\config('app.with_imap') && $folder->isImapReady()) {
            if (!\App\Backends\IMAP::updateSharedFolder($folder, $this->properties)) {
                throw new \Exception("Failed to update mailbox for shared folder {$this->folderId}.");
            }
        }
    }
}
