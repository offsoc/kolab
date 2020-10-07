<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The abstract \App\Jobs\UserJob implements the logic needed for all dispatchable Jobs related to
 * \App\User objects.
 *
 * ```php
 * $job = new \App\Jobs\User\CreateJob($userId);
 * $job->handle();
 * ```
 */
abstract class UserJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

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
     * The number of tries for this Job.
     *
     * @var int
     */
    public $tries = 5;

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

        $this->userEmail = $user->email;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle();

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
