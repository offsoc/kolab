<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserUnsuspend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:unsuspend {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a user suspension';

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

        $this->info("Found user {$user->id}");

        $user->unsuspend();
    }
}
