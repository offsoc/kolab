<?php

namespace Tests\Feature;

use App\Payment;
use App\Providers\PaymentProvider;
use App\Transaction;
use App\Wallet;
use App\VatRate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
        Payment::query()->delete();
        VatRate::query()->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');
        Payment::query()->delete();
        VatRate::query()->delete();

        parent::tearDown();
    }

    /**
     * Test createFromArray() and refund() methods
     */
    public function testCreateAndRefund(): void
    {
        Queue::fake();

        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        $vatRate = VatRate::create([
                'start' => now()->subDay(),
                'country' => 'US',
                'rate' => 7.5,
        ]);

        // Test required properties only
        $payment1Array = [
            'id' => 'test-payment2',
            'amount' => 10750,
            'currency' => 'USD',
            'currency_amount' => 9000,
            'type' => PaymentProvider::TYPE_ONEOFF,
            'wallet_id' => $wallet->id,
        ];

        $payment1 = Payment::createFromArray($payment1Array);

        $this->assertSame($payment1Array['id'], $payment1->id);
        $this->assertSame('', $payment1->provider);
        $this->assertSame('', $payment1->description);
        $this->assertSame(null, $payment1->vat_rate_id);
        $this->assertSame($payment1Array['amount'], $payment1->amount);
        $this->assertSame($payment1Array['amount'], $payment1->credit_amount);
        $this->assertSame($payment1Array['currency_amount'], $payment1->currency_amount);
        $this->assertSame($payment1Array['currency'], $payment1->currency);
        $this->assertSame($payment1Array['type'], $payment1->type);
        $this->assertSame(PaymentProvider::STATUS_OPEN, $payment1->status);
        $this->assertSame($payment1Array['wallet_id'], $payment1->wallet_id);
        $this->assertCount(1, Payment::where('id', $payment1->id)->get());

        // Test settable all properties
        $payment2Array = [
            'id' => 'test-payment',
            'provider' => 'mollie',
            'description' => 'payment description',
            'vat_rate_id' => $vatRate->id,
            'amount' => 10750,
            'credit_amount' => 10000,
            'currency' => $wallet->currency,
            'currency_amount' => 10750,
            'type' => PaymentProvider::TYPE_ONEOFF,
            'status' => PaymentProvider::STATUS_OPEN,
            'wallet_id' => $wallet->id,
        ];

        $payment2 = Payment::createFromArray($payment2Array);

        $this->assertSame($payment2Array['id'], $payment2->id);
        $this->assertSame($payment2Array['provider'], $payment2->provider);
        $this->assertSame($payment2Array['description'], $payment2->description);
        $this->assertSame($payment2Array['vat_rate_id'], $payment2->vat_rate_id);
        $this->assertSame($payment2Array['amount'], $payment2->amount);
        $this->assertSame($payment2Array['credit_amount'], $payment2->credit_amount);
        $this->assertSame($payment2Array['currency_amount'], $payment2->currency_amount);
        $this->assertSame($payment2Array['currency'], $payment2->currency);
        $this->assertSame($payment2Array['type'], $payment2->type);
        $this->assertSame($payment2Array['status'], $payment2->status);
        $this->assertSame($payment2Array['wallet_id'], $payment2->wallet_id);
        $this->assertSame($vatRate->id, $payment2->vatRate->id);
        $this->assertCount(1, Payment::where('id', $payment2->id)->get());

        $refundArray = [
            'id' => 'test-refund',
            'type' => PaymentProvider::TYPE_CHARGEBACK,
            'description' => 'test refund desc',
        ];

        // Refund amount is required
        $this->assertNull($payment2->refund($refundArray));

        // All needed info
        $refundArray['amount'] = 5000;

        $refund = $payment2->refund($refundArray);

        $this->assertSame($refundArray['id'], $refund->id);
        $this->assertSame($refundArray['description'], $refund->description);
        $this->assertSame(-5000, $refund->amount);
        $this->assertSame(-4651, $refund->credit_amount);
        $this->assertSame(-5000, $refund->currency_amount);
        $this->assertSame($refundArray['type'], $refund->type);
        $this->assertSame(PaymentProvider::STATUS_PAID, $refund->status);
        $this->assertSame($payment2->currency, $refund->currency);
        $this->assertSame($payment2->provider, $refund->provider);
        $this->assertSame($payment2->wallet_id, $refund->wallet_id);
        $this->assertSame($payment2->vat_rate_id, $refund->vat_rate_id);
        $wallet->refresh();
        $this->assertSame(-4651, $wallet->balance);
        $transaction = $wallet->transactions()->where('type', Transaction::WALLET_CHARGEBACK)->first();
        $this->assertSame(-4651, $transaction->amount);
        $this->assertSame($refundArray['description'], $transaction->description);

        $wallet->balance = 0;
        $wallet->save();

        // Test non-wallet currency
        $refundArray['id'] = 'test-refund-2';
        $refundArray['amount'] = 9000;
        $refundArray['type'] = PaymentProvider::TYPE_REFUND;

        $refund = $payment1->refund($refundArray);

        $this->assertSame($refundArray['id'], $refund->id);
        $this->assertSame($refundArray['description'], $refund->description);
        $this->assertSame(-10750, $refund->amount);
        $this->assertSame(-10750, $refund->credit_amount);
        $this->assertSame(-9000, $refund->currency_amount);
        $this->assertSame($refundArray['type'], $refund->type);
        $this->assertSame(PaymentProvider::STATUS_PAID, $refund->status);
        $this->assertSame($payment1->currency, $refund->currency);
        $this->assertSame($payment1->provider, $refund->provider);
        $this->assertSame($payment1->wallet_id, $refund->wallet_id);
        $this->assertSame($payment1->vat_rate_id, $refund->vat_rate_id);
        $wallet->refresh();
        $this->assertSame(-10750, $wallet->balance);
        $transaction = $wallet->transactions()->where('type', Transaction::WALLET_REFUND)->first();
        $this->assertSame(-10750, $transaction->amount);
        $this->assertSame($refundArray['description'], $transaction->description);
    }
}
