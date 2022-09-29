<?php

namespace App\Jobs\Resource;

use App\Jobs\ResourceJob;

class CreateJob extends ResourceJob
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
            $this->fail(new \Exception("Resource {$this->resourceId} is marked as deleted."));
            return;
        }

        if ($resource->trashed()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is actually deleted."));
            return;
        }

        $withLdap = \config('app.with_ldap');

        // see if the domain is ready
        $domain = $resource->domain();

        if (!$domain) {
            $this->fail(new \Exception("The domain for resource {$this->resourceId} does not exist."));
            return;
        }

        if ($domain->isDeleted()) {
            $this->fail(new \Exception("The domain for resource {$this->resourceId} is marked as deleted."));
            return;
        }

        if ($withLdap && !$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        if ($withLdap && !$resource->isLdapReady()) {
            \App\Backends\LDAP::createResource($resource);

            $resource->status |= \App\Resource::STATUS_LDAP_READY;
            $resource->save();
        }

        if (!$resource->isImapReady()) {
            if (!\App\Backends\IMAP::createResource($resource)) {
                throw new \Exception("Failed to create mailbox for resource {$this->resourceId}.");
            }

            $resource->status |= \App\Resource::STATUS_IMAP_READY;
        }

        $resource->status |= \App\Resource::STATUS_ACTIVE;
        $resource->save();
    }
}
