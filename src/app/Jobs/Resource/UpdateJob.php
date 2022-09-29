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

        // Cancel the update if the resource is deleted
        if ($resource->isDeleted()) {
            $this->delete();
            return;
        }

        if (\config('app.with_ldap') && $resource->isLdapReady()) {
            \App\Backends\LDAP::updateResource($resource);
        }

        if (\config('app.with_imap') && $resource->isImapReady()) {
            if (!\App\Backends\IMAP::updateResource($resource, $this->properties)) {
                throw new \Exception("Failed to update mailbox for resource {$this->resourceId}.");
            }
        }
    }
}
