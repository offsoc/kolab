<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class UndegradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:undegrade {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Un-degrade a user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $user->undegrade();
    }
}
