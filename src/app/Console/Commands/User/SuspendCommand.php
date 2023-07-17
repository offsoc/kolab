<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class SuspendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:suspend {user} {--comment=}';

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
            $this->error("User not found.");
            return 1;
        }

        $user->suspend();

        \App\EventLog::createFor($user, \App\EventLog::TYPE_SUSPENDED, $this->option('comment'));
    }
}
