<?php

namespace App\Console\Commands\Job;

use App\Console\Command;
use App\Wallet;

class WalletCharge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:walletcharge {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the WalletCharge job (again).";

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

        $job = new \App\Jobs\WalletCharge($wallet->id);
        $job->handle();
    }
}
