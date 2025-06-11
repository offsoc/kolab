<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\User;

class SetRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-role {user} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Set a role on the user";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));
        $role = $this->argument('role');

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        if ($role === 'null') {
            $this->info("Removing role.");
            $role = null;
        } elseif (!in_array($role, User::supportedRoles())) {
            $this->error("Invalid role.");
            return 1;
        }

        $user->role = $role;
        $user->save();
    }
}
