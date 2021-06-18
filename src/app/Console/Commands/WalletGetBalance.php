<?php

namespace App\Console\Commands;

use App\Console\Command;

class WalletGetBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:get-balance {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the balance of a wallet';

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

        $this->info($wallet->balance);
    }
}
