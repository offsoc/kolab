<?php

namespace App\Console\Commands;

use App\Console\Command;

class WalletSetBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:set-balance {wallet} {balance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the balance of a wallet';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wallet = $this->getWallet($this->argument('wallet'));

        if (!$wallet) {
            return 1;
        }

        $wallet->balance = (int) $this->argument('balance');
        $wallet->save();
    }
}
