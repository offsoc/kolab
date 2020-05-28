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
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Transaction::find($allegedly_unique)) {
                $transaction->{$transaction->getKeyName()} = $allegedly_unique;
                break;
            }
        }
    }
}
