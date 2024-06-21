<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class AliasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:aliases {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a user's aliases";

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

        foreach ($user->aliases()->pluck('alias')->all() as $alias) {
            $this->info($alias);
        }
    }
}
