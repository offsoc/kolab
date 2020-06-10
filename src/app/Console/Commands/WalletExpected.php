<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletExpected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:expected {--user=} {--non-zero}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show expected charges to wallets (for user)';

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
        if ($this->option('user')) {
            $user = \App\User::where('email', $this->option('user'))
                ->orWhere('id', $this->option('user'))->first();

            if (!$user) {
                return 1;
            }

            $wallets = $user->wallets;
        } else {
            $wallets = \App\Wallet::all();
        }

        foreach ($wallets as $wallet) {
            $charge = 0;
            $expected = $wallet->expectedCharges();

            if (!$wallet->owner) {
                \Log::debug("{$wallet->id} has no owner: {$wallet->user_id}");
                continue;
            }

            if ($this->option('non-zero') && $expected < 1) {
                continue;
            }

            $this->info(
                sprintf(
                    "expect charging wallet %s for user %s with %d",
                    $wallet->id,
                    $wallet->owner->email,
                    $expected
                )
            );
        }
    }
}
