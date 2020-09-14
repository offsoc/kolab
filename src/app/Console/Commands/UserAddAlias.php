<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\V4\UsersController;
use Illuminate\Console\Command;

class UserAddAlias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:add-alias {user} {alias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add an email alias to a user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = \App\User::where('email', $this->argument('user'))->first();

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
        $error = UsersController::validateEmail($alias, $controller, true);

        if ($error) {
            $this->error($error);
            return 1;
        }

        $user->aliases()->create(['alias' => $alias]);
    }
}
