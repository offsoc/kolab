<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        $wallet->balance = (int)($this->argument('balance'));
        $wallet->save();
    }
}
