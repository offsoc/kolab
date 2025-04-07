<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class VerifyCommand extends Command
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
            $this->error("User not found.");
            return 1;
        }

        \App\Jobs\User\VerifyJob::dispatchSync($user->id);

        // TODO: We should check the job result and print an error on failure
    }
}
