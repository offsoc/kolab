<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class WalletsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:wallets {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a user's wallets.";

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

        foreach ($user->wallets as $wallet) {
            $this->info("{$wallet->id} {$wallet->description}");
        }
    }
}
