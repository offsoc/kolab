<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class MollieInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mollie:info {user?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mollie information';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->argument('user')) {
            $user = User::where('email', $this->argument('user'))->first();

            if (!$user) {
                return 1;
            }

            $this->info("Found user: {$user->id}");

            $wallet = $user->wallets->first();
            $provider = new \App\Providers\Payment\Mollie();

            if ($mandate = $provider->getMandate($wallet)) {
                $amount = $wallet->getSetting('mandate_amount');
                $balance = $wallet->getSetting('mandate_balance') ?: 0;

                $this->info("Auto-payment: {$mandate['method']}");
                $this->info("    id: {$mandate['id']}");
                $this->info("    status: " . ($mandate['isPending'] ? 'pending' : 'valid'));
                $this->info("    amount: {$amount} {$wallet->currency}");
                $this->info("    min-balance: {$balance} {$wallet->currency}");
            } else {
                $this->info("Auto-payment: none");
            }

            // TODO: List user payments history
        } else {
            $this->info("Available payment methods:");

            foreach (mollie()->methods()->all() as $method) {
                $this->info("- {$method->description} ({$method->id}):");
                $this->info("    status: {$method->status}");
                $this->info(sprintf(
                    "    min: %s %s",
                    $method->minimumAmount->value,
                    $method->minimumAmount->currency
                ));
                if (!empty($method->maximumAmount)) {
                    $this->info(sprintf(
                        "    max: %s %s",
                        $method->maximumAmount->value,
                        $method->maximumAmount->currency
                    ));
                }
            }
        }
    }
}
