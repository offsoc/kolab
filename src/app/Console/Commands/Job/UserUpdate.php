<?php

namespace App\Console\Commands\Job;

use App\User;
use Illuminate\Console\Command;

class UserUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:userupdate {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the UserUpdate job (again).";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = User::where('email', $this->argument('user'))->first();

        if (!$user) {
            return 1;
        }

        $job = new \App\Jobs\User\UpdateJob($user->id);
        $job->handle();
    }
}
