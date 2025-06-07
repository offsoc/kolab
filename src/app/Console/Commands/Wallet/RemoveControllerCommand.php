<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class RemoveControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:remove-controller {wallet} {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a controller from a wallet';

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

        if ($wallet->owner?->id == $user->id) {
            $this->error("User is the wallet owner.");
            return 1;
        }

        if (!$wallet->isController($user)) {
            $this->error("User is not the wallet controller.");
            return 1;
        }

        $wallet->removeController($user);
    }
}
