<?php

namespace App\Console\Commands;

use App\Console\Command;

class WalletTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:transactions {--detail} {wallet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the transactions against a wallet.';

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

        $wallet->transactions()->orderBy('created_at')->each(function ($transaction) {
            $this->info(
                sprintf(
                    "%s: %s %s",
                    $transaction->id,
                    $transaction->created_at,
                    $transaction->toString()
                )
            );

            if ($this->option('detail')) {
                $elements = \App\Transaction::where('transaction_id', $transaction->id)
                    ->orderBy('created_at')->get();

                foreach ($elements as $element) {
                    $this->info(
                        sprintf(
                            "  + %s: %s",
                            $element->id,
                            $element->toString()
                        )
                    );
                }
            }
        });
    }
}
