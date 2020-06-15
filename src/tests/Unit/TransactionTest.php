<?php

namespace Tests\Unit;

use App\Transaction;
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
        $transactions = Transaction::limit(20)->get();

        foreach ($transactions as $transaction) {
            $this->assertNotNull($transaction->toString());
        }
    }

    public function testWalletPenalty()
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        $transaction = Transaction::create(
            [
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => Transaction::WALLET_PENALTY,
                'amount' => 9
            ]
        );

        $this->assertEquals($transaction->{'type'}, Transaction::WALLET_PENALTY);
    }

    public function testInvalidType()
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        $this->expectException(\Exception::class);

        $transaction = Transaction::create(
            [
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => 'invalid',
                'amount' => 9
            ]
        );
    }

    public function testEntitlementForWallet(): void
    {
        $transaction = \App\Transaction::where('object_type', \App\Wallet::class)
            ->whereIn('object_id', \App\Wallet::pluck('id'))->first();

        $entitlement = $transaction->entitlement();
        $this->assertNull($entitlement);
        $this->assertNotNull($transaction->wallet());
    }

    public function testWalletForEntitlement(): void
    {
        $transaction = \App\Transaction::where('object_type', \App\Entitlement::class)
            ->whereIn('object_id', \App\Entitlement::pluck('id'))->first();

        $wallet = $transaction->wallet();
        $this->assertNull($wallet);

        $this->assertNotNull($transaction->entitlement());
    }
}
