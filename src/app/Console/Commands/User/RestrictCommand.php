<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class RestrictCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:restrict {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restrict a user';

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

        $user->restrict();
    }
}
