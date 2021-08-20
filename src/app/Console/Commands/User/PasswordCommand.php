<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class PasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:password {user} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a users password';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'), true);

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $user->setPasswordAttribute($this->argument('password'));
        $user->save();
        $this->info('Password updated.');
    }
}
