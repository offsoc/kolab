<?php

namespace App\DataMigrator\Jobs;

use App\DataMigrator\Engine;
use App\DataMigrator\Interface\ItemSet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class ItemSetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var ItemSet Job data */
    protected $set;


    /**
     * Create a new job instance.
     *
     * @param ItemSet $set Set of Items to process
     *
     * @return void
     */
    public function __construct(ItemSet $set)
    {
        $this->set = $set;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $migrator = new Engine();
        $migrator->processItemSet($this->set);
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     *
     * @return void
     */
    public function failed(\Exception $exception)
    {
        // TODO: Count failed jobs in the queue
        // I'm not sure how to do this after the final failure (after X tries)
        // In other words how do we know all jobs in a queue finished (successfully or not)
        // Probably we have to set $tries = 1
    }
}
