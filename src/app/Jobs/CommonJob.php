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

    /** @var int The number of tries for this Job */
    public $tries = 24;

    /**
     * Execute the job.
     */
    abstract public function handle();

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60, 300, 3600];
    }

    /**
     * Log human-readable job title (at least contains job class name)
     */
    protected function logJobStart($ident = null): void
    {
        \Log::info('Starting ' . $this::class . ($ident ? " for {$ident}" : ''));
    }
}
