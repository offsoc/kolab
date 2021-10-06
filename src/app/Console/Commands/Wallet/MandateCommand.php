<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;
use App\Http\Controllers\API\V4\PaymentsController;

class MandateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:mandate {wallet} {--disable}{--enable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show wallet auto-payment mandate information.';

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

        $mandate = PaymentsController::walletMandate($wallet);

        if (!empty($mandate['id'])) {
            $disabled = $mandate['isDisabled'] ? 'Yes' : 'No';

            if ($this->option('disable') && $disabled == 'No') {
                $wallet->setSetting('mandate_disabled', 1);
                $disabled = 'Yes';
            } elseif ($this->option('enable') && $disabled == 'Yes') {
                $wallet->setSetting('mandate_disabled', null);
                $disabled = 'No';
            }

            $this->info("Auto-payment: {$mandate['method']}");
            $this->info("    id: {$mandate['id']}");
            $this->info("    status: " . ($mandate['isPending'] ? 'pending' : 'valid'));
            $this->info("    amount: {$mandate['amount']} {$wallet->currency}");
            $this->info("    min-balance: {$mandate['balance']} {$wallet->currency}");
            $this->info("    disabled: $disabled");
        } else {
            $this->info("Auto-payment: none");
        }
    }
}
