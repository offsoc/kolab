<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserVerify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:verify {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the state of a user account';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            return 1;
        }

        $this->info("Found user: {$user->id}");

        $job = new \App\Jobs\User\VerifyJob($user->id);
        $job->handle();
    }
}
