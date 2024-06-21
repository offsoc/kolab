<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class AddTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:add-transaction {wallet} {qty} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a transaction to a wallet';

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

        $message = (string) $this->option('message');

        if ($qty < 0) {
            $wallet->debit(-$qty, $message);
        } else {
            $wallet->credit($qty, $message);
        }
    }
}
