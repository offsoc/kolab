<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserWallets extends Command
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
    protected $description = 'List wallets for a user';

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

        foreach ($user->wallets as $wallet) {
            $this->info("{$wallet->id} {$wallet->description}");
        }
    }
}
