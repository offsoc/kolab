<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The abstract class implementing the logic needed for all dispatchable Jobs.
 * Includes default retry configuration.
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
     * The job deleted state.
     *
     * @var bool
     */
    protected $isDeleted = false;

    /**
     * The job released state.
     *
     * @var bool
     */
    protected $isReleased = false;

    /**
     * The number of tries for this Job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        // We need this for testing purposes
        $this->isDeleted = true;

        if ($this->job) {
            $this->job->delete();
        }
    }

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

    /**
     * Release the job back into the queue.
     *
     * @param int $delay Time in seconds
     * @return void
     */
    public function release($delay = 0)
    {
        // We need this for testing purposes
        $this->isReleased = true;

        if ($this->job) {
            $this->job->release($delay);
        } else {
            // $this->job is only set when the job is dispatched, not if manually executed by calling handle().
            // When manually executed, release() does nothing, and we thus throw an exception.
            throw new \Exception("Attempted to release a manually executed job");
        }
    }

    /**
     * Determine if the job has been deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    /**
     * Check if the job was released
     *
     * @return bool
     */
    public function isReleased(): bool
    {
        return $this->isReleased;
    }

    /**
     * Log human-readable job title (at least contains job class name)
     */
    public function logJobStart($ident = null): void
    {
        \Log::info('Starting ' . $this::class . ($ident ? " for {$ident}" : ''));
    }
}
