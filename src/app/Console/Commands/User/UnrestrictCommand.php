<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class UnrestrictCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:unrestrict {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Un-restrict a user';

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

        $user->unrestrict();
    }
}
