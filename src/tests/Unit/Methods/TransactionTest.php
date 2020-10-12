<?php

namespace Tests\Unit\Methods;

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

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->transaction = new \App\Transaction();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    public function testSetTypeAttributeAnyValid()
    {
        foreach ($this->transactionTypes as $type) {
            $this->transaction->{'type'} = $type;

            $this->assertSame($this->transaction->{'type'}, $type);
        }
    }

    public function testSetTypeAttributeInvalid()
    {
        $this->expectException(\Exception::class);

        $this->transaction->{'type'} = 1;
    }
}
