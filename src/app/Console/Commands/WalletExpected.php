<?php

namespace App\Console\Commands;

use App\Domain;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WalletExpected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:expected';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show expected charges to wallets';

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
        $wallets = \App\Wallet::all();

        foreach ($wallets as $wallet) {
            $charge = 0;
            $expected = $wallet->expectedCharges();

            if ($expected > 0) {
                $this->info("expect charging wallet {$wallet->id} for user {$wallet->owner->email} with {$expected}");
            }
        }
    }
}
