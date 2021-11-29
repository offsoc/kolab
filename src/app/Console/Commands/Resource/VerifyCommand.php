<?php

namespace App\Console\Commands\Resource;

use App\Console\Command;

class VerifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resource:verify {resource}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the state of a resource';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $resource = $this->getResource($this->argument('resource'));

        if (!$resource) {
            $this->error("Resource not found.");
            return 1;
        }

        $job = new \App\Jobs\Resource\VerifyJob($resource->id);
        $job->handle();

        // TODO: We should check the job result and print an error on failure
    }
}
