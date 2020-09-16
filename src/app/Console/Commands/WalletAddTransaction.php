<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletAddTransaction extends Command
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
        $wallet = \App\Wallet::find($this->argument('wallet'));

        if (!$wallet) {
            return 1;
        }

        $qty = (int) $this->argument('qty');

        $message = $this->option('message');

        if ($qty < 0) {
            $wallet->debit($qty, $message);
        } else {
            $wallet->credit($qty, $message);
        }
    }
}
