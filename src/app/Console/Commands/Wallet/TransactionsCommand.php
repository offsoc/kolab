<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class TransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:transactions {--detail} {--balance} {wallet}';

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
            $this->error("Wallet not found.");
            return 1;
        }

        $withDetail = $this->option('detail');
        $balanceMode = $this->option('balance');
        $balance = 0;

        $transactions = $wallet->transactions()->orderBy('created_at')->cursor();

        foreach ($transactions as $transaction) {
            $balance += $transaction->amount;

            $this->info(
                sprintf(
                    "%s: %s %s",
                    $transaction->id,
                    $transaction->created_at,
                    $transaction->toString()
                )
                . ($balanceMode ? sprintf(' (balance: %s)', $wallet->money($balance)) : '')
            );

            if ($withDetail) {
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
        }
    }
}
