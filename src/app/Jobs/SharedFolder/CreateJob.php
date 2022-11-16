<?php

namespace App\Jobs\SharedFolder;

use App\Jobs\SharedFolderJob;

class CreateJob extends SharedFolderJob
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
            $this->fail(new \Exception("Shared folder {$this->folderId} is marked as deleted."));
            return;
        }

        if ($folder->trashed()) {
            $this->fail(new \Exception("Shared folder {$this->folderId} is actually deleted."));
            return;
        }

        $withLdap = \config('app.with_ldap');

        // see if the domain is ready
        $domain = $folder->domain();

        if (!$domain) {
            $this->fail(new \Exception("The domain for shared folder {$this->folderId} does not exist."));
            return;
        }

        if ($domain->isDeleted()) {
            $this->fail(new \Exception("The domain for shared folder {$this->folderId} is marked as deleted."));
            return;
        }

        if ($withLdap && !$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        if ($withLdap && !$folder->isLdapReady()) {
            \App\Backends\LDAP::createSharedFolder($folder);

            $folder->status |= \App\SharedFolder::STATUS_LDAP_READY;
            $folder->save();
        }

        if (!$folder->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!\App\Backends\IMAP::createSharedFolder($folder)) {
                    throw new \Exception("Failed to create mailbox for shared folder {$this->folderId}.");
                }
            } else {
                $folderName = $folder->getSetting('folder');

                if ($folderName && !\App\Backends\IMAP::verifySharedFolder($folderName)) {
                    $this->release(15);
                    return;
                }
            }

            $folder->status |= \App\SharedFolder::STATUS_IMAP_READY;
        }

        $folder->status |= \App\SharedFolder::STATUS_ACTIVE;
        $folder->save();
    }
}
