<?php

namespace App\Console\Commands\Wallet;

use Illuminate\Console\Command;

class AddPenaltyTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:add-penalty {wallet} {cents} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a penalty transaction to a wallet ' .
        '(deduct a positive quantity of cents)';

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

        $cents = (int) $this->argument('cents');

        if ($cents < 0) {
            $this->error("Can't penalize with a negative amount.");
            return 1;
        }

        if (!$this->option('message')) {
            $this->error("Can't penalize a wallet without a message.");
            return 1;
        }

        $message = $this->option('message');

        $wallet->balance -= $cents;
        $wallet->save();

        \App\Transaction::create(
            [
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => \App\Transaction::WALLET_PENALTY,
                'amount' => $cents
            ]
        );
    }
}
