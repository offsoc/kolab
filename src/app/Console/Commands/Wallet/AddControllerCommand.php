<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class AddControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:add-controller {wallet} {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a controller to a wallet';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wallet = $this->getWallet($this->argument('wallet'));

        if (!$wallet) {
            $this->error("Wallet not found.");
            return 1;
        }

        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        $user_wallet = $user->wallet();

        if (!$user_wallet || $user_wallet->id !== $wallet->id) {
            $this->error("User does not belong to this wallet.");
            return 1;
        }

        $wallet->addController($user);
    }
}
