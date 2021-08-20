<?php

namespace App\Observers;

use App\Transaction;

class TransactionObserver
{
    /**
     * Ensure the transaction ID is a custom ID (uuid).
     *
     * @param \App\Transaction $transaction The transaction object
     *
     * @return void
     */
    public function creating(Transaction $transaction): void
    {
        if (!isset($transaction->user_email)) {
            $transaction->user_email = \App\Utils::userEmailOrNull();
        }
    }
}
