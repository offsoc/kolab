<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class UntilCommand extends Command
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

        $until = $wallet->balanceLastsUntil();

        $this->info("Lasts until: " . ($until ? $until->toDateString() : 'unknown'));
    }
}
