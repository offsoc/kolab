<?php

namespace App\Jobs\Resource;

use App\Jobs\ResourceJob;

class UpdateJob extends ResourceJob
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

        // Cancel the update if the resource is deleted or not yet in LDAP
        if (!$resource->isLdapReady() || $resource->isDeleted()) {
            $this->delete();
            return;
        }

        \App\Backends\LDAP::updateResource($resource);
    }
}
