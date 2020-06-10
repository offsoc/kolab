<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletUntil extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:until {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show until when the balance on a wallet lasts.';

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

        $lastsUntil = $wallet->balanceLastsUntil();

        $this->info("Lasts until: {$lastsUntil}");
    }
}
