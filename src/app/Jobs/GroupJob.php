<?php

namespace App\Jobs;

/**
 * The abstract \App\Jobs\GroupJob implements the logic needed for all dispatchable Jobs related to
 * \App\Group objects.
 *
 * ```php
 * $job = new \App\Jobs\Group\CreateJob($groupId);
 * $job->handle();
 * ```
 */
abstract class GroupJob extends CommonJob
{
    /**
     * The ID for the \App\Group. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete \App\Group object.
     *
     * @var int
     */
    protected $groupId;

    /**
     * The \App\Group email property, for legibility in the queue management.
     *
     * @var string
     */
    protected $groupEmail;

    /**
     * Create a new job instance.
     *
     * @param int $groupId The ID for the group to create.
     *
     * @return void
     */
    public function __construct(int $groupId)
    {
        $this->groupId = $groupId;

        $group = $this->getGroup();

        if ($group) {
            $this->groupEmail = $group->email;
        }
    }

    /**
     * Get the \App\Group entry associated with this job.
     *
     * @return \App\Group|null
     *
     * @throws \Exception
     */
    protected function getGroup()
    {
        $group = \App\Group::withTrashed()->find($this->groupId);

        if (!$group) {
            $this->fail(new \Exception("Group {$this->groupId} could not be found in the database."));
        }

        return $group;
    }
}
