<?php

namespace App\Jobs;

/**
 * The abstract \App\Jobs\ResourceJob implements the logic needed for all dispatchable Jobs related to
 * \App\Resource objects.
 *
 * ```php
 * $job = new \App\Jobs\Resource\CreateJob($resourceId);
 * $job->handle();
 * ```
 */
abstract class ResourceJob extends CommonJob
{
    /**
     * The ID for the \App\Resource. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete \App\Resource object.
     *
     * @var int
     */
    protected $resourceId;

    /**
     * The \App\Resource email property, for legibility in the queue management.
     *
     * @var string
     */
    protected $resourceEmail;

    /**
     * Create a new job instance.
     *
     * @param int $resourceId The ID for the resource to process.
     *
     * @return void
     */
    public function __construct(int $resourceId)
    {
        $this->resourceId = $resourceId;

        $resource = $this->getResource();

        if ($resource) {
            $this->resourceEmail = $resource->email;
        }
    }

    /**
     * Get the \App\Resource entry associated with this job.
     *
     * @return \App\Resource|null
     *
     * @throws \Exception
     */
    protected function getResource()
    {
        $resource = \App\Resource::withTrashed()->find($this->resourceId);

        if (!$resource) {
            // The record might not exist yet in case of a db replication environment
            // This will release the job and delay another attempt for 5 seconds
            if ($this instanceof Resource\CreateJob) {
                $this->release(5);
                return null;
            }

            $this->fail(new \Exception("Resource {$this->resourceId} could not be found in the database."));
        }

        return $resource;
    }
}
