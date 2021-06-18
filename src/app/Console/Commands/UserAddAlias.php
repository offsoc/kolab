<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\Http\Controllers\API\V4\UsersController;

class UserAddAlias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:add-alias {--force} {user} {alias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add an email alias to a user (forcefully)';

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

        $alias = \strtolower($this->argument('alias'));

        // Check if the alias already exists
        if ($user->aliases()->where('alias', $alias)->first()) {
            $this->error("Address is already assigned to the user.");
            return 1;
        }

        $controller = $user->wallet()->owner;

        // Validate the alias
        $error = UsersController::validateAlias($alias, $controller);

        if ($error) {
            if (!$this->option('force')) {
                $this->error($error);
                return 1;
            }
        }

        $user->aliases()->create(['alias' => $alias]);
    }
}
