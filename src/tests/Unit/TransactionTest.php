<?php

namespace Tests\Unit;

use Tests\TestCase;

class TransactionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testLabel()
    {
        $transactions = \App\Transaction::all();

        foreach ($transactions as $transaction) {
            $this->assertNotNull($transaction->toString());
        }
    }

    public function testWalletPenalty()
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        $transaction = \App\Transaction::create(
            [
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => \App\Transaction::WALLET_PENALTY,
                'amount' => 9
            ]
        );

        $this->assertEquals($transaction->{'type'}, 'penalty');
    }

    public function testInvalidType()
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        $this->expectException(\Exception::class);

        $transaction = \App\Transaction::create(
            [
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => 'invalid',
                'amount' => 9
            ]
        );
    }
}
