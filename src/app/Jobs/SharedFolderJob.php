<?php

namespace App\Jobs;

/**
 * The abstract \App\Jobs\SharedFolderJob implements the logic needed for all dispatchable Jobs related to
 * \App\SharedFolder objects.
 *
 * ```php
 * $job = new \App\Jobs\SharedFolder\CreateJob($folderId);
 * $job->handle();
 * ```
 */
abstract class SharedFolderJob extends CommonJob
{
    /**
     * The ID for the \App\SharedFolder. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete \App\SharedFolder object.
     *
     * @var int
     */
    protected $folderId;
    /**
     * The \App\SharedFolder email property, for legibility in the queue management.
     *
     * @var string
     */
    protected $folderEmail;

    /**
     * Old values of the shared folder properties on update (key -> value)
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Create a new job instance.
     *
     * @param int   $folderId   The ID for the shared folder to process
     * @param array $properties Old values of the shared folder properties on update (key -> value)
     *
     * @return void
     */
    public function __construct(int $folderId, array $properties = [])
    {
        $this->folderId = $folderId;
        $this->properties = $properties;

        $folder = $this->getSharedFolder();

        if ($folder) {
            $this->folderEmail = $folder->email;
        }
    }

    /**
     * Get the \App\SharedFolder entry associated with this job.
     *
     * @return \App\SharedFolder|null
     *
     * @throws \Exception
     */
    protected function getSharedFolder()
    {
        $folder = \App\SharedFolder::withTrashed()->find($this->folderId);

        if (!$folder) {
            // The record might not exist yet in case of a db replication environment
            // This will release the job and delay another attempt for 5 seconds
            if ($this instanceof SharedFolder\CreateJob) {
                $this->release(5);
                return null;
            }

            $this->fail(new \Exception("Shared folder {$this->folderId} could not be found in the database."));
        }

        return $folder;
    }
}
