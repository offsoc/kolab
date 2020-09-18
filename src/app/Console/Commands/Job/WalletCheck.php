<?php

namespace App\Console\Commands\Job;

use App\Wallet;
use Illuminate\Console\Command;

class WalletCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:walletcheck {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the WalletCheck job (again).";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wallet = Wallet::find($this->argument('wallet'));

        if (!$wallet) {
            return 1;
        }

        $job = new \App\Jobs\WalletCheck($wallet);
        $job->handle();
    }
}