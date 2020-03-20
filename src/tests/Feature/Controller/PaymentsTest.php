<?php

namespace Tests\Feature\Controller;

use App\Payment;
use App\Wallet;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use \Tests\MollieMocksTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        $john->setSetting('mollie_id', null);
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        $john->setSetting('mollie_id', null);
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);

        parent::tearDown();
    }

    /**
     * Test creating a payment and receiving a status via webhook)
     *
     * @group mollie
     */
    public function testStoreAndWebhook(): void
    {
        // Unauth access not allowed
        $response = $this->post("api/v4/payments", []);
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');

        $post = ['amount' => -1];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame('The amount must be at least 1.', $json['errors']['amount'][0]);

        $post = ['amount' => 1234];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertRegExp('|^https://www.mollie.com|', $json['redirectUrl']);

        $wallet = $user->wallets()->first();
        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertSame(1234, $payment->amount);
        $this->assertSame('Kolab Now Payment', $payment->description);
        $this->assertSame('open', $payment->status);
        $this->assertEquals(0, $wallet->balance);

        // Test the webhook
        // Note: Webhook end-point does not require authentication

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "paid",
            // Status is not enough, paidAt is used to distinguish the state
            "paidAt" => date('c'),
            "mode" => "test",
/*
            "createdAt" => "2018-03-20T13:13:37+00:00",
            "amount" => {
                "value" => "10.00",
                "currency" => "EUR"
            },
            "description" => "Order #12345",
            "method" => null,
            "metadata" => {
                "order_id" => "12345"
            },
            "isCancelable" => false,
            "locale" => "nl_NL",
            "restrictPaymentMethodsToCountry" => "NL",
            "expiresAt" => "2018-03-20T13:28:37+00:00",
            "details" => null,
            "profileId" => "pfl_QkEhN94Ba",
            "sequenceType" => "oneoff",
            "redirectUrl" => "https://webshop.example.org/order/12345/",
            "webhookUrl" => "https://webshop.example.org/payments/webhook/",
*/
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // a request to the Mollie payments API. We cannot force Mollie
        // to make the payment status change.
        $responseStack = $this->mockMollie();
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Verify "paid -> open -> paid" scenario, assert that balance didn't change
        $mollie_response['status'] = 'open';
        unset($mollie_response['paidAt']);
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $mollie_response['status'] = 'paid';
        $mollie_response['paidAt'] = date('c');
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);
    }

    public function testDirectCharge(): void
    {
        $this->markTestIncomplete();
    }
}
