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
        if (!$resource->trashed()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is not deleted."));
            return;
        }

        if ($resource->isDeleted()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is already marked as deleted."));
            return;
        }

        if (\config('app.with_ldap') && $resource->isLdapReady()) {
            \App\Backends\LDAP::deleteResource($resource);

            $resource->status ^= \App\Resource::STATUS_LDAP_READY;
            $resource->save();
        }

        if ($resource->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!\App\Backends\IMAP::deleteResource($resource)) {
                    throw new \Exception("Failed to delete mailbox for resource {$this->resourceId}.");
                }
            }

            $resource->status ^= \App\Resource::STATUS_IMAP_READY;
        }

        $resource->status |= \App\Resource::STATUS_DELETED;
        $resource->save();
    }
}
