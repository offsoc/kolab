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

        if ($folder->isLdapReady()) {
            $this->fail(new \Exception("Shared folder {$this->folderId} is already marked as ldap-ready."));
            return;
        }

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

        if (!$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        \App\Backends\LDAP::createSharedFolder($folder);

        $folder->status |= \App\SharedFolder::STATUS_LDAP_READY;
        $folder->save();
    }
}