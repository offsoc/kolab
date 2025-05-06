<?php

namespace App\Observers;

use App\Transaction;
use App\Utils;

class TransactionObserver
{
    /**
     * Ensure the transaction ID is a custom ID (uuid).
     *
     * @param Transaction $transaction The transaction object
     */
    public function creating(Transaction $transaction): void
    {
        if (!isset($transaction->user_email)) {
            $transaction->user_email = Utils::userEmailOrNull();
        }
    }
}
