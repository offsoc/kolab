<?php

namespace App\Console\Commands;

use App\Console\Command;

class JobExecuteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:execute {job} {object}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute a job.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $job = $this->argument('job');
        $object = $this->argument('object');

        $job = str_replace('/', '\\', $job);

        if (preg_match('/^(User|Domain|Group|Resource|SharedFolder|Wallet).[a-zA-Z]+$/', $job, $m)) {
            $object = $this->{'get' . $m[1]}($object);
        } else {
            $this->error("Invalid or unsupported job name.");
            return 1;
        }

        if (empty($object)) {
            $this->error("Object not found.");
            return 1;
        }

        $job = 'App\\Jobs\\' . $job;

        $job = new $job($object->id);
        $job->handle();
    }
}
