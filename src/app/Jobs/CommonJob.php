<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The abstract \App\Jobs\DomainJob implements the logic needed for all dispatchable Jobs related to
 * \App\Domain objects.
 *
 * ```php
 * $job = new \App\Jobs\Domain\CreateJob($domainId);
 * $job->handle();
 * ```
 */
abstract class CommonJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The failure message.
     *
     * @var string
     */
    public $failureMessage;

    /**
     * The number of tries for this Job.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param \Throwable|null $e An Exception
     *
     * @return void
     */
    public function fail($e = null)
    {
        // Save the message, for testing purposes
        $this->failureMessage = $e->getMessage();

        // @phpstan-ignore-next-line
        if ($this->job) {
            $this->job->fail($e);
        }
    }

    /**
     * Check if the job has failed
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->failureMessage !== null;
    }
}
