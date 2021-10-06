<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class GetDiscountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:get-discount {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the existing discount to a wallet, if any.';

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

        if (!$wallet->discount) {
            $this->info("No discount on this wallet.");
            return 0;
        }

        $this->info($wallet->discount->discount);
    }
}
