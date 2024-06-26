<?php

namespace App\Jobs\DataMigrator;

use App\DataMigrator\EWS;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class EWSItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var array Job data */
    protected $data;


    /**
     * Create a new job instance.
     *
     * @param array $data Job data (folder/item and queue parameters)
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ews = new EWS;
        $ews->processItem($this->data);
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
