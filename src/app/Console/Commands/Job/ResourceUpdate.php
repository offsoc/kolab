<?php

namespace App\Console\Commands\Job;

use App\Console\Command;

class ResourceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:resourceupdate {resource}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the resource update job (again).";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $resource = $this->getResource($this->argument('resource'));

        if (!$resource) {
            return 1;
        }

        $job = new \App\Jobs\Resource\UpdateJob($resource->id);
        $job->handle();
    }
}
