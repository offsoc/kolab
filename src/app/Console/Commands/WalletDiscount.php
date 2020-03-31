<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletDiscount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:discount {wallet} {discount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply a discount to a wallet';

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
        $wallet = \App\Wallet::where('id', $this->argument('wallet'))->first();

        if (!$wallet) {
            return 1;
        }

        // FIXME: Using '0' for delete might be not that obvious

        if ($this->argument('discount') === '0') {
            $wallet->discount()->dissociate();
        } else {
            $discount = \App\Discount::find($this->argument('discount'));

            if (!$discount) {
                return 1;
            }

            $wallet->discount()->associate($discount);
        }

        $wallet->save();
    }
}
