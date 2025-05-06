<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\Providers\PaymentProvider;
use App\User;

class StripeInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:info {user?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stripe information';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->argument('user')) {
            $user = $this->getUser($this->argument('user'));

            if (!$user) {
                return 1;
            }

            $this->info("Found user: {$user->id}");

            $wallet = $user->wallets->first();
            $provider = PaymentProvider::factory('stripe');

            if ($mandate = $provider->getMandate($wallet)) {
                $amount = $wallet->getSetting('mandate_amount');
                $balance = $wallet->getSetting('mandate_balance') ?: 0;
                $status = 'invalid';

                if ($mandate['isPending']) {
                    $status = 'pending';
                } elseif ($mandate['isValid']) {
                    $status = 'valid';
                }

                if ($wallet->getSetting('mandate_disabled')) {
                    $status .= ' (disabled)';
                }

                $this->info("Auto-payment: {$mandate['method']}");
                $this->info("    id: {$mandate['id']}");
                $this->info("    status: {$status}");
                $this->info("    amount: {$amount} {$wallet->currency}");
                $this->info("    min-balance: {$balance} {$wallet->currency}");
            } else {
                $this->info("Auto-payment: none");
            }

            // TODO: List user payments history
        }
        // TODO: Fetch some info/stats from Stripe
    }
}
