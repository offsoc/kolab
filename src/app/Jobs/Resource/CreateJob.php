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

        if ($resource->isLdapReady()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is already marked as ldap-ready."));
            return;
        }

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

        if (!$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        \App\Backends\LDAP::createResource($resource);

        $resource->status |= \App\Resource::STATUS_LDAP_READY;
        $resource->save();
    }
}
