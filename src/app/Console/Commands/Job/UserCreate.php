<?php

namespace App\Console\Commands\Job;

use App\User;
use Illuminate\Console\Command;

class UserCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:usercreate {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the UserCreate job (again).";

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

        $job = new \App\Jobs\UserCreate($user);
        $job->handle();
    }
}
