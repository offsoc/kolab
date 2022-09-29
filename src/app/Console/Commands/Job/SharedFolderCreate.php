<?php

namespace App\Console\Commands\Job;

use App\Console\Command;

class SharedFolderCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:sharedfoldercreate {folder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the shared folder creation job (again).";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $folder = $this->getSharedFolder($this->argument('folder'));

        if (!$folder) {
            return 1;
        }

        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();
    }
}
