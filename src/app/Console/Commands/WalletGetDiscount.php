<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletGetDiscount extends Command
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
        $wallet = \App\Wallet::find($this->argument('wallet'));

        if (!$wallet) {
            return 1;
        }

        if (!$wallet->discount) {
            $this->info("No discount on this wallet.");
            return 0;
        }

        $this->info($wallet->discount->discount);
    }
}
