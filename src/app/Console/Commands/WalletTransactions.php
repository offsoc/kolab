<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        foreach ($wallet->transactions() as $transaction) {
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
                            "  + %s: %s %s",
                            $element->id,
                            $element->created_at,
                            $element->toString()
                        )
                    );
                }
            }
        }
    }
}
