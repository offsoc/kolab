<?php

namespace App\Console\Commands;

use App\User;
use App\Console\Command;

class UserSuspend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:suspend {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend a user';

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

        $user->suspend();
    }
}
