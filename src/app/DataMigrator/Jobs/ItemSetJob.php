<?php

namespace App\DataMigrator\Jobs;

use App\DataMigrator\Engine;
use App\DataMigrator\Interface\ItemSet;
use App\DataMigrator\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ItemSetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 5;

    /** @var ItemSet Job data */
    protected $set;

    /**
     * Create a new job instance.
     *
     * @param ItemSet $set Set of Items to process
     */
    public function __construct(ItemSet $set)
    {
        $this->set = $set;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $migrator = new Engine();
        $migrator->processItemSet($this->set);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        // This method is executed after all tries fail
        // TODO: Queue::find($this->set->folder->queueId)->bumpJobsFailed();
    }
}
