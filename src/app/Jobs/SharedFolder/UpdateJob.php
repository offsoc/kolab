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

        // Cancel the update if the folder is deleted or not yet in LDAP
        if (!$folder->isLdapReady() || $folder->isDeleted()) {
            $this->delete();
            return;
        }

        \App\Backends\LDAP::updateSharedFolder($folder);
    }
}
