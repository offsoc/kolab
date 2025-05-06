<?php

namespace App\Jobs;

use App\Resource;

/**
 * The abstract \App\Jobs\ResourceJob implements the logic needed for all dispatchable Jobs related to
 * Resource objects.
 *
 * ```php
 * $job = new \App\Jobs\Resource\CreateJob($resourceId);
 * $job->handle();
 * ```
 */
abstract class ResourceJob extends CommonJob
{
    /**
     * Old values of the resource properties on update (key -> value)
     *
     * @var array
     */
    protected $properties = [];

    /**
     * The ID for the Resource. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete Resource object.
     *
     * @var int
     */
    protected $resourceId;

    /**
     * The Resource email property, for legibility in the queue management.
     *
     * @var string
     */
    protected $resourceEmail;

    /**
     * Create a new job instance.
     *
     * @param int   $resourceId the ID for the resource to process
     * @param array $properties Old values of the resource properties on update
     */
    public function __construct(int $resourceId, array $properties = [])
    {
        $this->resourceId = $resourceId;
        $this->properties = $properties;

        $resource = $this->getResource();

        if ($resource) {
            $this->resourceEmail = $resource->email;
        }
    }

    /**
     * Get the Resource entry associated with this job.
     *
     * @return Resource|null
     *
     * @throws \Exception
     */
    protected function getResource()
    {
        $resource = Resource::withTrashed()->find($this->resourceId);

        if (!$resource) {
            // The record might not exist yet in case of a db replication environment
            // This will release the job and delay another attempt for 5 seconds
            if ($this instanceof \App\Jobs\Resource\CreateJob) {
                $this->release(5);
                return null;
            }

            $this->fail("Resource {$this->resourceId} could not be found in the database.");
        }

        return $resource;
    }
}
