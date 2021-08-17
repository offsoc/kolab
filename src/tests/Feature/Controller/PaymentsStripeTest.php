<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Transaction;
use App\Wallet;
use App\WalletSetting;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\StripeMocksTrait;

class PaymentsStripeTest extends TestCase
{
    use StripeMocksTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // All tests in this file use Stripe
        \config(['services.payment_provider' => 'stripe']);

        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
        Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_CREDIT)->delete();
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
        Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_CREDIT)->delete();

        parent::tearDown();
    }

    /**
     * Test creating/updating/deleting an outo-payment mandate
     *
     * @group stripe
     */
    public function testMandates(): void
    {
        Bus::fake();

        // Unauth access not allowed
        $response = $this->get("api/v4/payments/mandate");
        $response->assertStatus(401);
        $response = $this->post("api/v4/payments/mandate", []);
        $response->assertStatus(401);
        $response = $this->put("api/v4/payments/mandate", []);
        $response->assertStatus(401);
        $response = $this->delete("api/v4/payments/mandate");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Test creating a mandate (invalid input)
        $post = [];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertSame('The amount field is required.', $json['errors']['amount'][0]);
        $this->assertSame('The balance field is required.', $json['errors']['balance'][0]);

        // Test creating a mandate (invalid input)
        $post = ['amount' => 100, 'balance' => 'a'];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame('The balance must be a number.', $json['errors']['balance'][0]);

        // Test creating a mandate (invalid input)
        $post = ['amount' => -100, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = $wallet->money(PaymentProvider::MIN_AMOUNT);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        // Test creating a mandate (negative balance, amount too small)
        Wallet::where('id', $wallet->id)->update(['balance' => -2000]);
        $post = ['amount' => PaymentProvider::MIN_AMOUNT / 100, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("The specified amount does not cover the balance on the account.", $json['errors']['amount']);

        // Test creating a mandate (valid input)
        $post = ['amount' => 20.10, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^cs_test_|', $json['id']);

        // Assert the proper payment amount has been used
        // Stripe in 'setup' mode does not allow to set the amount
        $payment = Payment::where('wallet_id', $wallet->id)->first();
        $this->assertSame(0, $payment->amount);
        $this->assertSame($user->tenant->title . " Auto-Payment Setup", $payment->description);
        $this->assertSame(PaymentProvider::TYPE_MANDATE, $payment->type);

        // Test fetching the mandate information
        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertSame(false, $json['isDisabled']);

        // We would have to invoke a browser to accept the "first payment" to make
        // the mandate validated/completed. Instead, we'll mock the mandate object.
        $setupIntent = '{
            "id": "AAA",
            "object": "setup_intent",
            "created": 123456789,
            "payment_method": "pm_YYY",
            "status": "succeeded",
            "usage": "off_session",
            "customer": null
        }';

        $paymentMethod = '{
            "id": "pm_YYY",
            "object": "payment_method",
            "card": {
                "brand": "visa",
                "country": "US",
                "last4": "4242"
            },
            "created": 123456789,
            "type": "card"
        }';

        $client = $this->mockStripe();
        $client->addResponse($setupIntent);
        $client->addResponse($paymentMethod);

        // As we do not use checkout page, we do not receive a webworker request
        // I.e. we have to fake the mandate id
        $wallet = $user->wallets()->first();
        $wallet->setSetting('stripe_mandate_id', 'AAA');
        $wallet->setSetting('mandate_disabled', 1);

        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertEquals('Visa (**** **** **** 4242)', $json['method']);
        $this->assertSame(false, $json['isPending']);
        $this->assertSame(true, $json['isValid']);
        $this->assertSame(true, $json['isDisabled']);

        // Test updating mandate details (invalid input)
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = 1000;
        $wallet->save();
        $user->refresh();
        $post = [];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertSame('The amount field is required.', $json['errors']['amount'][0]);
        $this->assertSame('The balance field is required.', $json['errors']['balance'][0]);

        $post = ['amount' => -100, 'balance' => 0];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        // Test updating a mandate (valid input)
        $client->addResponse($setupIntent);
        $client->addResponse($paymentMethod);

        $post = ['amount' => 30.10, 'balance' => 10];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertEquals(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertEquals(10, $wallet->getSetting('mandate_balance'));
        $this->assertSame('AAA', $json['id']);
        $this->assertFalse($json['isDisabled']);

        // Test updating a disabled mandate (invalid input)
        $wallet->setSetting('mandate_disabled', 1);
        $wallet->balance = -2000;
        $wallet->save();
        $user->refresh(); // required so the controller sees the wallet update from above

        $post = ['amount' => 15.10, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame('The specified amount does not cover the balance on the account.', $json['errors']['amount']);

        // Test updating a disabled mandate (valid input)
        $client->addResponse($setupIntent);
        $client->addResponse($paymentMethod);

        $post = ['amount' => 30, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame('AAA', $json['id']);
        $this->assertFalse($json['isDisabled']);

        Bus::assertDispatchedTimes(\App\Jobs\WalletCharge::class, 1);
        Bus::assertDispatched(\App\Jobs\WalletCharge::class, function ($job) use ($wallet) {
            $job_wallet = $this->getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });


        $this->unmockStripe();

        // TODO: Delete mandate
    }

    /**
     * Test creating a payment and receiving a status via webhook
     *
     * @group stripe
     */
    public function testStoreAndWebhook(): void
    {
        Bus::fake();

        // Unauth access not allowed
        $response = $this->post("api/v4/payments", []);
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        $post = ['amount' => -1];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = $wallet->money(PaymentProvider::MIN_AMOUNT);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        // Invalid currency
        $post = ['amount' => '12.34', 'currency' => 'FOO', 'methodId' => 'creditcard'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(500);

        // Successful payment
        $post = ['amount' => '12.34', 'currency' => 'CHF', 'methodId' => 'creditcard'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^cs_test_|', $json['id']);

        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertSame(1234, $payment->amount);
        $this->assertSame($user->tenant->title . ' Payment', $payment->description);
        $this->assertSame('open', $payment->status);
        $this->assertEquals(0, $wallet->balance);

        // Test the webhook

        $post = [
            'id' => "evt_1GlZ814fj3SIEU8wtxMZ4Nsa",
            'object' => "event",
            'api_version' => "2020-03-02",
            'created' => 1590147209,
            'data' => [
                'object' => [
                    'id' => $payment->id,
                    'object' => "payment_intent",
                    'amount' => 1234,
                    'amount_capturable' => 0,
                    'amount_received' => 1234,
                    'capture_method' => "automatic",
                    'client_secret' => "pi_1GlZ7w4fj3SIEU8w1RlBpN4l_secret_UYRNDTUUU7nkYHpOLZMb3uf48",
                    'confirmation_method' => "automatic",
                    'created' => 1590147204,
                    'currency' => "chf",
                    'customer' => "cus_HKDZ53OsKdlM83",
                    'last_payment_error' => null,
                    'livemode' => false,
                    'metadata' => [],
                    'receipt_email' => "payment-test@kolabnow.com",
                    'status' => "succeeded"
                ]
            ],
            'type' => "payment_intent.succeeded"
        ];

        // Test payment succeeded event
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(1234, $transaction->amount);
        $this->assertSame(
            "Payment transaction {$payment->id} using Stripe",
            $transaction->description
        );

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);

        // Test that balance didn't change if the same event is posted
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Test for payment failure ('failed' status)
        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $post['type'] = "payment_intent.payment_failed";
        $post['data']['object']['status'] = 'failed';

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_FAILED, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);

        // Test for payment failure ('canceled' status)
        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $post['type'] = "payment_intent.canceled";
        $post['data']['object']['status'] = 'canceled';

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_CANCELED, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);
    }

    /**
     * Test receiving webhook request for setup intent
     *
     * @group stripe
     */
    public function testCreateMandateAndWebhook(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        Wallet::where('id', $wallet->id)->update(['balance' => -1000]);

        // Test creating a mandate (valid input)
        $post = ['amount' => 20.10, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $payment = $wallet->payments()->first();

        $this->assertSame(PaymentProvider::STATUS_OPEN, $payment->status);
        $this->assertSame(PaymentProvider::TYPE_MANDATE, $payment->type);
        $this->assertSame(0, $payment->amount);

        $post = [
            'id' => "evt_1GlZ814fj3SIEU8wtxMZ4Nsa",
            'object' => "event",
            'api_version' => "2020-03-02",
            'created' => 1590147209,
            'data' => [
                'object' => [
                    'id' => $payment->id,
                    'object' => "setup_intent",
                    'client_secret' => "pi_1GlZ7w4fj3SIEU8w1RlBpN4l_secret_UYRNDTUUU7nkYHpOLZMb3uf48",
                    'created' => 1590147204,
                    'customer' => "cus_HKDZ53OsKdlM83",
                    'last_setup_error' => null,
                    'metadata' => [],
                    'status' => "succeeded"
                ]
            ],
            'type' => "setup_intent.succeeded"
        ];

        Bus::fake();

        // Test payment succeeded event
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $payment->refresh();

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->status);
        $this->assertSame($payment->id, $wallet->fresh()->getSetting('stripe_mandate_id'));

        // Expect a WalletCharge job if the balance is negative
        Bus::assertDispatchedTimes(\App\Jobs\WalletCharge::class, 1);
        Bus::assertDispatched(\App\Jobs\WalletCharge::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        // TODO: test other setup_intent.* events
    }

    /**
     * Test automatic payment charges
     *
     * @group stripe
     */
    public function testTopUpAndWebhook(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Stripe API does not allow us to create a mandate easily
        // That's why we we'll mock API responses
        // Create a fake mandate
        $wallet->setSettings([
            'mandate_amount' => 20.10,
            'mandate_balance' => 10,
            'stripe_mandate_id' => 'AAA',
        ]);

        $setupIntent = json_encode([
                "id" => "AAA",
                "object" => "setup_intent",
                "created" => 123456789,
                "payment_method" => "pm_YYY",
                "status" => "succeeded",
                "usage" => "off_session",
                "customer" => null
        ]);

        $paymentMethod = json_encode([
                "id" => "pm_YYY",
                "object" => "payment_method",
                "card" => [
                    "brand" => "visa",
                    "country" => "US",
                    "last4" => "4242"
                ],
                "created" => 123456789,
                "type" => "card"
        ]);

        $paymentIntent = json_encode([
                "id" => "pi_XX",
                "object" => "payment_intent",
                "created" => 123456789,
                "amount" => 2010,
                "currency" => "chf",
                "description" => $user->tenant->title . " Recurring Payment"
        ]);

        $client = $this->mockStripe();
        $client->addResponse($setupIntent);
        $client->addResponse($paymentMethod);
        $client->addResponse($setupIntent);
        $client->addResponse($paymentIntent);
        $client->addResponse($setupIntent);
        $client->addResponse($paymentMethod);

        // Expect a recurring payment as we have a valid mandate at this point
        $result = PaymentsController::topUpWallet($wallet);
        $this->assertTrue($result);

        // Check that the payments table contains a new record with proper amount
        // There should be two records, one for the first payment and another for
        // the recurring payment
        $this->assertCount(1, $wallet->payments()->get());
        $payment = $wallet->payments()->first();
        $this->assertSame(2010, $payment->amount);
        $this->assertSame($user->tenant->title . " Recurring Payment", $payment->description);
        $this->assertSame("pi_XX", $payment->id);

        // Expect no payment if the mandate is disabled
        $wallet->setSetting('mandate_disabled', 1);
        $result = PaymentsController::topUpWallet($wallet);
        $this->assertFalse($result);
        $this->assertCount(1, $wallet->payments()->get());

        // Expect no payment if balance is ok
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = 1000;
        $wallet->save();
        $result = PaymentsController::topUpWallet($wallet);
        $this->assertFalse($result);
        $this->assertCount(1, $wallet->payments()->get());

        // Expect no payment if the top-up amount is not enough
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = -2050;
        $wallet->save();

        $result = PaymentsController::topUpWallet($wallet);
        $this->assertFalse($result);
        $this->assertCount(1, $wallet->payments()->get());

        Bus::assertDispatchedTimes(\App\Jobs\PaymentMandateDisabledEmail::class, 1);
        Bus::assertDispatched(\App\Jobs\PaymentMandateDisabledEmail::class, function ($job) use ($wallet) {
            $job_wallet = $this->getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        // Expect no payment if there's no mandate
        $wallet->setSetting('mollie_mandate_id', null);
        $wallet->balance = 0;
        $wallet->save();
        $result = PaymentsController::topUpWallet($wallet);
        $this->assertFalse($result);
        $this->assertCount(1, $wallet->payments()->get());

        Bus::assertDispatchedTimes(\App\Jobs\PaymentMandateDisabledEmail::class, 1);

        $this->unmockStripe();

        // Test webhook

        $post = [
            'id' => "evt_1GlZ814fj3SIEU8wtxMZ4Nsa",
            'object' => "event",
            'api_version' => "2020-03-02",
            'created' => 1590147209,
            'data' => [
                'object' => [
                    'id' => $payment->id,
                    'object' => "payment_intent",
                    'amount' => 2010,
                    'capture_method' => "automatic",
                    'created' => 1590147204,
                    'currency' => "chf",
                    'customer' => "cus_HKDZ53OsKdlM83",
                    'last_payment_error' => null,
                    'metadata' => [],
                    'receipt_email' => "payment-test@kolabnow.com",
                    'status' => "succeeded"
                ]
            ],
            'type' => "payment_intent.succeeded"
        ];

        // Test payment succeeded event
        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(2010, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(2010, $transaction->amount);
        $this->assertSame(
            "Auto-payment transaction {$payment->id} using Stripe",
            $transaction->description
        );

        // Assert that email notification job has been dispatched
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 1);
        Bus::assertDispatched(\App\Jobs\PaymentEmail::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

        Bus::fake();

        // Test for payment failure ('failed' status)
        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $wallet->setSetting('mandate_disabled', null);

        $post['type'] = "payment_intent.payment_failed";
        $post['data']['object']['status'] = 'failed';

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $wallet->refresh();

        $this->assertSame(PaymentProvider::STATUS_FAILED, $payment->fresh()->status);
        $this->assertEquals(2010, $wallet->balance);
        $this->assertTrue(!empty($wallet->getSetting('mandate_disabled')));

        // Assert that email notification job has been dispatched
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 1);
        Bus::assertDispatched(\App\Jobs\PaymentEmail::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

        Bus::fake();

        // Test for payment failure ('canceled' status)
        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $post['type'] = "payment_intent.canceled";
        $post['data']['object']['status'] = 'canceled';

        $response = $this->webhookRequest($post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_CANCELED, $payment->fresh()->status);
        $this->assertEquals(2010, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);
    }

    /**
     * Generate Stripe-Signature header for a webhook payload
     */
    protected function webhookRequest($post)
    {
        $secret = \config('services.stripe.webhook_secret');
        $ts = time();

        $payload = "$ts." . json_encode($post);
        $sig = sprintf('t=%d,v1=%s', $ts, \hash_hmac('sha256', $payload, $secret));

        return $this->withHeaders(['Stripe-Signature' => $sig])
            ->json('POST', "api/webhooks/payment/stripe", $post);
    }

    /**
     * Test listing payment methods
     *
     * @group stripe
     */
    public function testListingPaymentMethods(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . PaymentProvider::TYPE_ONEOFF);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('creditcard', $json[0]['id']);
        $this->assertSame('paypal', $json[1]['id']);

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . PaymentProvider::TYPE_RECURRING);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('creditcard', $json[0]['id']);
    }
}
