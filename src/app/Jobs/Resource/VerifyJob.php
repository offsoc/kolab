<?php

namespace App\Jobs\Resource;

use App\Jobs\ResourceJob;

class VerifyJob extends ResourceJob
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

        // the resource was already verified
        if ($resource->isImapReady()) {
            $this->fail(new \Exception("Resource {$this->resourceId} is already verified."));
            return;
        }

        $folder = $resource->getSetting('folder');

        if ($folder && \App\Backends\IMAP::verifySharedFolder($folder)) {
            $resource->status |= \App\Resource::STATUS_IMAP_READY;
            $resource->status |= \App\Resource::STATUS_ACTIVE;
            $resource->save();
        }
    }
}
