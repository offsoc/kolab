<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// phpcs:ignore
class TransactionAmountFix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $negatives = [
            \App\Transaction::WALLET_CHARGEBACK,
            \App\Transaction::WALLET_DEBIT,
            \App\Transaction::WALLET_PENALTY,
            \App\Transaction::WALLET_REFUND,
        ];

        $query = "UPDATE transactions SET amount = amount * -1"
            . " WHERE type IN (" . implode(',', array_fill(0, count($negatives), '?')) . ")";

        DB::select($query, $negatives);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::select("UPDATE transactions SET amount = amount * -1 WHERE amount < 0");
    }
}
