<?php

namespace App\Jobs\Resource;

use App\Jobs\ResourceJob;

class DeleteJob extends ResourceJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $resource = $this->getResource();

        if (!$resource) {
            return;
        }

        // sanity checks
        if ($resource->isDeleted()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is already marked as deleted."));
            return;
        }

        \App\Backends\LDAP::deleteResource($resource);

        $resource->status |= \App\Resource::STATUS_DELETED;

        if ($resource->isLdapReady()) {
            $resource->status ^= \App\Resource::STATUS_LDAP_READY;
        }

        if ($resource->isImapReady()) {
            $resource->status ^= \App\Resource::STATUS_IMAP_READY;
        }

        $resource->save();
    }
}
