<?php

namespace App\Console\Commands\Wallet;

use App\Payment;
use App\Utils;
use App\Console\Command;

class AddPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:add-payment {wallet} {qty} {--message=} {--backdate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a payment to a wallet';

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

        $qty = (int) $this->argument('qty');

        // $message = (string) $this->option('message');

        $payment = Payment::createFromArray([
            'id' => Utils::randStr(32),
            'amount' => $qty,
            'currency' => $wallet->currency,
            'currency_amount' => $qty,
            'type' => Payment::TYPE_ONEOFF,
            'wallet_id' => $wallet->id,
            'status' => Payment::STATUS_PAID,
        ]);

        ;
        if ($backdate = (string) $this->option('backdate')) {
            $payment->created_at = \Carbon\Carbon::parse($backdate);
            $payment->updated_at = $payment->created_at;
            $payment->save();
        }
        $this->info($payment->id);
    }
}
