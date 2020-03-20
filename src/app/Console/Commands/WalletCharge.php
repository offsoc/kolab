<?php

namespace App\Console\Commands;

use App\Domain;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WalletCharge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:charge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge wallets';

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
            $charge = $wallet->expectedCharges();

            if ($charge > 0) {
                $this->info(
                    "charging wallet {$wallet->id} for user {$wallet->owner->email} with {$charge}"
                );

                $wallet->chargeEntitlements();

                if ($wallet->balance < 0) {
                    // Disabled for now
                    // \App\Jobs\WalletPayment::dispatch($wallet);
                }
            }
        }
    }
}
