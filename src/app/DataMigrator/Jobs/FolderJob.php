<?php

namespace App\DataMigrator\Jobs;

use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FolderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 5;

    /** @var Folder Job data */
    protected $folder;

    /**
     * Create a new job instance.
     *
     * @param Folder $folder Folder to process
     */
    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $migrator = new Engine();
        $migrator->processFolder($this->folder);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        // This method is executed after all tries fail
        // TODO: Queue::find($this->folder->queueId)->bumpJobsFailed();
    }
}
