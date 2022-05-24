<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Transaction;
use App\Wallet;
use App\WalletSetting;
use App\Utils;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\BrowserAddonTrait;
use Tests\CoinbaseMocksTrait;

class PaymentsCoinbaseTest extends TestCase
{
    use CoinbaseMocksTrait;
    use BrowserAddonTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        \config(['services.payment_provider' => '']);

        Utils::setTestExchangeRates(['EUR' => '0.90503424978382']);
        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
        $types = [
            Transaction::WALLET_CREDIT,
            Transaction::WALLET_REFUND,
            Transaction::WALLET_CHARGEBACK,
        ];
        Transaction::where('object_id', $wallet->id)->whereIn('type', $types)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
        $types = [
            Transaction::WALLET_CREDIT,
            Transaction::WALLET_REFUND,
            Transaction::WALLET_CHARGEBACK,
        ];
        Transaction::where('object_id', $wallet->id)->whereIn('type', $types)->delete();
        Utils::setTestExchangeRates([]);

        parent::tearDown();
    }

    /**
     * Test creating a payment and receiving a status via webhook
     *
     * @group coinbase
     */
    public function testStoreAndWebhook(): void
    {
        Bus::fake();

        // Unauth access not allowed
        $response = $this->post("api/v4/payments", []);
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Invalid amount
        $post = ['amount' => -1];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = $wallet->money(PaymentProvider::MIN_AMOUNT);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        // Invalid currency
        $post = ['amount' => '12.34', 'currency' => 'FOO', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(500);

        // Rate limit exceeded
        $coinbase_response = [
            'error' => [
                'type' => 'rate_limit_exceeded',
                'message' => 'Rate limit exceeded',
            ],
        ];

        $responseStack = $this->mockCoinbase();
        $responseStack->append(new Response(429, [], json_encode($coinbase_response)));

        $post = ['amount' => '12.34', 'currency' => 'BTC', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(500);

        // Rate limit exceeded
        $coinbase_response = [
            'error' => [
                'type' => 'invalid_request',
                'message' => 'Required parameter missing: name',
            ],
        ];

        $responseStack = $this->mockCoinbase();
        $responseStack->append(new Response(400, [], json_encode($coinbase_response)));

        $post = ['amount' => '12.34', 'currency' => 'BTC', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(500);

        // Successful payment
        $coinbase_response = [
            'reason' => 'Created',
            'data' => [
                'code' => 'test123',
                'hosted_url' => 'https://commerce.coinbase.com',
                'pricing' => [
                    'bitcoin' => [
                        'amount' => 0.0000005,
                    ],
                ],
            ],
        ];

        $responseStack = $this->mockCoinbase();
        $responseStack->append(new Response(201, [], json_encode($coinbase_response)));

        $post = ['amount' => '12.34', 'currency' => 'BTC', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^https://commerce.coinbase.com|', $json['newWindowUrl']);

        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertSame(1234, $payment->amount);
        $this->assertSame(5, $payment->currency_amount);
        $this->assertSame('BTC', $payment->currency);
        $this->assertSame($user->tenant->title . ' Payment', $payment->description);
        $this->assertSame('open', $payment->status);
        $this->assertEquals(0, $wallet->balance);

        // Test the webhook
        $post = [
            'event' =>
            [
                'api_version' => '2018-03-22',
                'data' => [
                    'code' => $payment->id,
                ],
                'type' => 'charge:resolved',
            ],
        ];
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(1234, $transaction->amount);
        $this->assertSame(
            "Payment transaction {$payment->id} using Coinbase",
            $transaction->description
        );

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);

        // Verify "paid -> open -> paid" scenario, assert that balance didn't change
        $post = [
            'event' =>
            [
                'api_version' => '2018-03-22',
                'data' => [
                    'code' => $payment->id,
                ],
                'type' => 'charge:created',
            ],
        ];
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $post = [
            'event' =>
            [
                'api_version' => '2018-03-22',
                'data' => [
                    'code' => $payment->id,
                ],
                'type' => 'charge:resolved',
            ],
        ];

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Test for payment failure
        Bus::fake();

        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $post = [
            'event' =>
            [
                'api_version' => '2018-03-22',
                'data' => [
                    'code' => $payment->id,
                ],
                'type' => 'charge:failed',
            ],
        ];

        $response = $this->webhookRequest($post);

        $response->assertStatus(200);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);
    }


    /**
     * Test creating a payment and receiving a status via webhook using a foreign currency
     *
     * @group coinbase
     */
    public function testStoreAndWebhookForeignCurrency(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Successful payment in BTC
        $coinbase_response = [
            'reason' => 'Created',
            'data' => [
                'code' => 'test123',
                'hosted_url' => 'www.hosted.com',
                'pricing' => [
                    'bitcoin' => [
                        'amount' => 0.0000005,
                    ],
                ],
            ],
        ];

        $responseStack = $this->mockCoinbase();
        $responseStack->append(new Response(201, [], json_encode($coinbase_response)));
        $post = ['amount' => '12.34', 'currency' => 'BTC', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $payment = $wallet->payments()
            ->where('currency', 'BTC')->get()->last();

        $this->assertSame(1234, $payment->amount);
        $this->assertSame(5, $payment->currency_amount);
        $this->assertSame('BTC', $payment->currency);
        $this->assertEquals(0, $wallet->balance);

        $post = [
            'event' =>
            [
                'api_version' => '2018-03-22',
                'data' => [
                    'code' => $payment->id,
                ],
                'type' => 'charge:resolved',
            ],
        ];

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);
    }


    /**
     * Generate Coinbase-Signature header for a webhook payload
     */
    protected function webhookRequest($post)
    {
        $secret = \config('services.coinbase.webhook_secret');

        $payload = json_encode($post);
        $sig = \hash_hmac('sha256', $payload, $secret);

        return $this->withHeaders(['x-cc-webhook-signature' => $sig])
            ->json('POST', "api/webhooks/payment/coinbase", $post);
    }


    /**
     * Test listing a pending payment
     *
     * @group coinbase
     */
    public function testListingPayments(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');

        //Empty response
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertSame(false, $json['hasPending']);

        $wallet = $user->wallets()->first();

        // Successful payment
        $coinbase_response = [
            'reason' => 'Created',
            'data' => [
                'code' => 'test123',
                'hosted_url' => 'www.hosted.com',
                'pricing' => [
                    'bitcoin' => [
                        'amount' => 0.0000005,
                    ],
                ],
            ],
        ];

        $responseStack = $this->mockCoinbase();
        $responseStack->append(new Response(201, [], json_encode($coinbase_response)));
        $post = ['amount' => '12.34', 'currency' => 'BTC', 'methodId' => 'bitcoin'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        //A response
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(PaymentProvider::STATUS_OPEN, $json['list'][0]['status']);
        $this->assertSame('CHF', $json['list'][0]['currency']);
        $this->assertSame(PaymentProvider::TYPE_ONEOFF, $json['list'][0]['type']);
        $this->assertSame(1234, $json['list'][0]['amount']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertSame(true, $json['hasPending']);

        // Set the payment to paid
        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];

        $payment->status = PaymentProvider::STATUS_PAID;
        $payment->save();

        // They payment should be gone from the pending list now
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();
        $this->assertSame('success', $json['status']);
        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertSame(false, $json['hasPending']);
    }

    /**
     * Test listing payment methods
     *
     * @group coinbase
     */
    public function testListingPaymentMethods(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . PaymentProvider::TYPE_ONEOFF);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame('bitcoin', $json[3]['id']);
        $this->assertSame('BTC', $json[3]['currency']);

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . PaymentProvider::TYPE_RECURRING);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(1, $json);
    }
}
