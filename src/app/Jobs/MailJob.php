<?php

namespace App\Jobs;

use App\Enums\Queue;
use Illuminate\Queue\SerializesModels;

/**
 * An abstract class for all e-mailing jobs
 */
abstract class MailJob extends CommonJob
{
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 5;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var string|null The name of the queue the job should be sent to. */
    public $queue = Queue::Mail->value;

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 120, 600, 3600];
    }
}
