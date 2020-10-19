<?php

namespace App\Jobs;

/**
 * The abstract \App\Jobs\UserJob implements the logic needed for all dispatchable Jobs related to
 * \App\User objects.
 *
 * ```php
 * $job = new \App\Jobs\User\CreateJob($userId);
 * $job->handle();
 * ```
 */
abstract class UserJob extends CommonJob
{
    /**
     * The ID for the \App\User. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete \App\User object.
     *
     * @var int
     */
    protected $userId;

    /**
     * The \App\User email property, for legibility in the queue management.
     *
     * @var string
     */
    protected $userEmail;

    /**
     * Create a new job instance.
     *
     * @param int $userId The ID for the user to create.
     *
     * @return void
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;

        $user = $this->getUser();

        if ($user) {
            $this->userEmail = $user->email;
        }
    }

    /**
     * Get the \App\User entry associated with this job.
     *
     * @return \App\User|null
     *
     * @throws \Exception
     */
    protected function getUser()
    {
        $user = \App\User::withTrashed()->find($this->userId);

        if (!$user) {
            $this->fail(new \Exception("User {$this->userId} could not be found in the database."));
        }

        return $user;
    }
}
