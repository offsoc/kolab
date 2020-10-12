<?php

namespace Tests\Functional\Methods;

use Tests\TestCase;

class TransactionTest extends TestCase
{
    private $transaction;

    private $transactionTypes = [
        \App\Transaction::ENTITLEMENT_BILLED,
        \App\Transaction::ENTITLEMENT_CREATED,
        \App\Transaction::ENTITLEMENT_DELETED,
        \App\Transaction::WALLET_AWARD,
        \App\Transaction::WALLET_CREDIT,
        \App\Transaction::WALLET_DEBIT,
        \App\Transaction::WALLET_PENALTY,
    ];

    public function testShortDescription()
    {
        // requires the entire application to be bootstrapped.
        $this->markTestSkipped('requires the entire application to be bootstrapped, effectively functional');

        foreach ($this->transactionTypes as $type) {
            $this->transaction->{'type'} = $type;

            $this->assertNotNull($this->transaction->shortDescription());
        }
    }
}
