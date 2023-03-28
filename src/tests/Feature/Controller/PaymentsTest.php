<?php

namespace Tests\Feature\Controller;

use App\Payment;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Payment::query()->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Payment::query()->delete();

        parent::tearDown();
    }

    /**
     * Test fetching payment status (GET /payments/status)
     */
    public function testPaymentStatus(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/payments/status");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Test no payment
        $response = $this->actingAs($user)->get("api/v4/payments/status");
        $response->assertStatus(404);

        $payment = Payment::create([
                'id' => 'tr_123456',
                'status' => Payment::STATUS_PAID,
                'amount' => 123,
                'credit_amount' => 123,
                'currency_amount' => 123,
                'currency' => 'CHF',
                'type' => Payment::TYPE_ONEOFF,
                'wallet_id' => $wallet->id,
                'provider' => 'mollie',
                'description' => 'test',
        ]);

        // Test a paid payment
        $response = $this->actingAs($user)->get("api/v4/payments/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($payment->id, $json['id']);
        $this->assertSame($payment->status, $json['status']);
        $this->assertSame($payment->type, $json['type']);
        $this->assertSame($payment->description, $json['description']);
        $this->assertSame("The payment has been completed successfully.", $json['statusMessage']);

        // Test a pending payment
        $payment->status = Payment::STATUS_OPEN;
        $payment->save();
        $response = $this->actingAs($user)->get("api/v4/payments/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($payment->id, $json['id']);
        $this->assertSame("The payment hasn't been completed yet. Checking the status...", $json['statusMessage']);

        // TODO: Test other statuses
    }
}
