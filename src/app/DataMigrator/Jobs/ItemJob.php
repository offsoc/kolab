<?php

namespace App\DataMigrator\Jobs;

use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ItemJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 5;

    /** @var Item Job data */
    protected $item;

    /**
     * Create a new job instance.
     *
     * @param Item $item Item to process
     */
    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $migrator = new Engine();
        $migrator->processItem($this->item);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        // This method is executed after all tries fail
        // TODO: Queue::find($this->item->folder->queueId)->bumpJobsFailed();
    }
}
