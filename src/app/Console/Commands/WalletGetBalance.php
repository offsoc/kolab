<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        $this->info($wallet->balance);
    }
}
