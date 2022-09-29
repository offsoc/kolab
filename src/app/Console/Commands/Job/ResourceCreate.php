<?php

namespace App\Console\Commands\Job;

use App\Console\Command;

class ResourceCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:resourcecreate {resource}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the resource creation job (again).";

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

        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();
    }
}
