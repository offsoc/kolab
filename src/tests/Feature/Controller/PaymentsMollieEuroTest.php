<?php

namespace Tests\Feature\Controller;

use App\Jobs\Mail\PaymentJob;
use App\Jobs\Mail\PaymentMandateDisabledJob;
use App\Jobs\Wallet\ChargeJob;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Transaction;
use App\Wallet;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mollie\Api\Types\RefundStatus;
use Tests\BrowserAddonTrait;
use Tests\TestCase;

class PaymentsMollieEuroTest extends TestCase
{
    use BrowserAddonTrait;

    protected const API_URL = 'https://api.mollie.com/v2';

    protected function setUp(): void
    {
        parent::setUp();

        if (!\config('services.mollie.key')) {
            $this->markTestSkipped('No MOLLIE_KEY');
        }

        // All tests in this file use Mollie
        \config(['services.payment_provider' => 'mollie']);
    }

    protected function tearDown(): void
    {
        if (\config('services.mollie.key')) {
            $this->deleteTestUser('euro@' . \config('app.domain'));
        }

        parent::tearDown();
    }

    /**
     * Test creating/updating/deleting an outo-payment mandate
     *
     * @group mollie
     * @group slow
     */
    public function testMandates(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/payments/mandate");
        $response->assertStatus(401);
        $response = $this->post("api/v4/payments/mandate", []);
        $response->assertStatus(401);
        $response = $this->put("api/v4/payments/mandate", []);
        $response->assertStatus(401);
        $response = $this->delete("api/v4/payments/mandate");
        $response->assertStatus(401);

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();

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

        // Test creating a mandate (amount smaller than the minimum value)
        $post = ['amount' => -100, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = $wallet->money(Payment::MIN_AMOUNT);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);
        $this->assertMatchesRegularExpression("/[0-9.,]+ €\\.$/", $json['errors']['amount']);

        // Test creating a mandate (negative balance, amount too small)
        Wallet::where('id', $wallet->id)->update(['balance' => -2000]);
        $post = ['amount' => Payment::MIN_AMOUNT / 100, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("The specified amount does not cover the balance on the account.", $json['errors']['amount']);

        // Test creating a mandate (valid input)
        $json = $this->createMollieMandate(
            $wallet->fresh(),
            ['amount' => 20.10, 'balance' => 0, 'methodId' => PaymentProvider::METHOD_CREDITCARD]
        );

        $mandate_id = $json['mandateId'];

        // Assert the proper payment amount has been used
        $payment = Payment::where('id', $json['id'])->first();
        $this->assertSame(2010, $payment->amount);
        $this->assertSame($wallet->id, $payment->wallet_id);
        $this->assertSame($user->tenant->title . " Auto-Payment Setup", $payment->description);
        $this->assertSame(Payment::TYPE_MANDATE, $payment->type);

        // Test fetching the mandate information
        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(20.10, $json['amount']);
        $this->assertSame(0, $json['balance']);
        $this->assertTrue(in_array($json['method'], ['Mastercard (**** **** **** 9399)', 'Credit Card']));
        $this->assertFalse($json['isPending']);
        $this->assertTrue($json['isValid']);
        $this->assertFalse($json['isDisabled']);

        $wallet = $user->wallets()->first();
        $wallet->setSetting('mandate_disabled', 1);

        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(20.10, $json['amount']);
        $this->assertSame(0, $json['balance']);
        $this->assertTrue(in_array($json['method'], ['Mastercard (**** **** **** 9399)', 'Credit Card']));
        $this->assertFalse($json['isPending']);
        $this->assertTrue($json['isValid']);
        $this->assertTrue($json['isDisabled']);

        Bus::fake();
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = 1000;
        $wallet->save();

        // Test updating mandate details (invalid input)
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
        $this->assertMatchesRegularExpression("/[0-9.,]+ €\\.$/", $json['errors']['amount']);

        // Test updating a mandate (valid input)
        $post = ['amount' => 30.10, 'balance' => 10];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame($mandate_id, $json['id']);
        $this->assertFalse($json['isDisabled']);

        $wallet->refresh();

        $this->assertSame(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertSame(10, $wallet->getSetting('mandate_balance'));

        Bus::assertDispatchedTimes(ChargeJob::class, 0);

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
        $post = ['amount' => 30, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame($mandate_id, $json['id']);
        $this->assertFalse($json['isDisabled']);

        Bus::assertDispatchedTimes(ChargeJob::class, 1);
        Bus::assertDispatched(ChargeJob::class, function ($job) use ($wallet) {
            $job_wallet_id = $this->getObjectProperty($job, 'walletId');
            return $job_wallet_id === $wallet->id;
        });

        // Delete mandate
        $response = $this->actingAs($user)->delete("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been removed.', $json['message']);

        // Confirm the mandate does not exist (is not valid) anymore
        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse($json['isValid']);
        $this->assertNull($wallet->fresh()->getSetting('mollie_mandate_id'));

        // Test Mollie's "410 Gone" response handling when fetching the mandate info
        // It is expected to remove the mandate reference
        $mollie_response = [
            'status' => 410,
            'title' => "Gone",
            'detail' => "You are trying to access an object, which has previously been deleted",
            '_links' => [
                'documentation' => [
                    'href' => "https://docs.mollie.com/errors",
                    'type' => "text/html",
                ],
            ],
        ];

        $mollieId = $wallet->getSetting('mollie_id');
        Http::fakeClear()->fake([
            self::API_URL . "/customers/{$mollieId}/mandates/123" => Http::response($mollie_response, 410, []),
        ]);

        $wallet->fresh()->setSetting('mollie_mandate_id', '123');

        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse(array_key_exists('id', $json));
        $this->assertFalse(array_key_exists('method', $json));
        $this->assertNull($wallet->fresh()->getSetting('mollie_mandate_id'));
    }

    /**
     * Test creating a payment and receiving a status via webhook
     *
     * @group mollie
     */
    public function testStoreAndWebhook(): void
    {
        Bus::fake();

        // Unauth access not allowed
        $response = $this->post("api/v4/payments", []);
        $response->assertStatus(401);

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();

        // Invalid amount
        $post = ['amount' => -1];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = $wallet->money(Payment::MIN_AMOUNT);
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);
        $this->assertMatchesRegularExpression("/[0-9.,]+ €\\.$/", $json['errors']['amount']);

        // Invalid currency
        $post = ['amount' => '12.34', 'currency' => 'FOO', 'methodId' => 'creditcard'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(500);

        // Successful payment
        $post = ['amount' => '12.34', 'currency' => 'EUR', 'methodId' => 'creditcard'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^https://www.mollie.com|', $json['redirectUrl']);

        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertSame(1234, $payment->amount);
        $this->assertSame(1234, $payment->currency_amount);
        $this->assertSame('EUR', $payment->currency);
        $this->assertSame($user->tenant->title . ' Payment', $payment->description);
        $this->assertSame('open', $payment->status);
        $this->assertSame(0, $wallet->balance);

        // Test the webhook
        // Note: Webhook end-point does not require authentication

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "paid",
            // Status is not enough, paidAt is used to distinguish the state
            "paidAt" => date('c'),
            "mode" => "test",
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // a request to the Mollie payments API. We cannot force Mollie
        // to make the payment status change.
        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(1234, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(1234, $transaction->amount);
        $this->assertSame(
            "Payment transaction {$payment->id} using Mollie",
            $transaction->description
        );

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(PaymentJob::class, 0);

        // Verify "paid -> open -> paid" scenario, assert that balance didn't change
        $mollie_response['status'] = 'open';
        unset($mollie_response['paidAt']);

        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(1234, $wallet->fresh()->balance);

        $mollie_response['status'] = 'paid';
        $mollie_response['paidAt'] = date('c');

        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(1234, $wallet->fresh()->balance);

        // Test for payment failure
        Bus::fake();

        $payment->refresh();
        $payment->status = Payment::STATUS_OPEN;
        $payment->save();

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "failed",
            "mode" => "test",
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // a request to the Mollie payments API. We cannot force Mollie
        // to make the payment status change.
        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(1234, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(PaymentJob::class, 0);
    }

    /**
     * Test automatic payment charges
     *
     * @group mollie
     * @group slow
     */
    public function testTopUp(): void
    {
        Bus::fake();

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();

        // Create a valid mandate first (balance=0, so there's no extra payment yet)
        $this->createMollieMandate($wallet, ['amount' => 20.10, 'balance' => 0, 'methodId' => 'creditcard']);

        $wallet->setSetting('mandate_balance', 10);

        // Expect a recurring payment as we have a valid mandate at this point
        // and the balance is below the threshold
        $result = $wallet->topUp();
        $this->assertTrue($result);

        // Check that the payments table contains a new record with proper amount.
        // There should be two records, one for the mandate payment and another for
        // the top-up payment
        $payments = $wallet->payments()->orderBy('amount')->get();
        $this->assertCount(2, $payments);
        $this->assertSame(0, $payments[0]->amount);
        $this->assertSame(0, $payments[0]->currency_amount);
        $this->assertSame(2010, $payments[1]->amount);
        $this->assertSame(2010, $payments[1]->currency_amount);
        $payment = $payments[1];

        // In mollie we don't have to wait for a webhook, the response to
        // PaymentIntent already sets the status to 'paid', so we can test
        // immediately the balance update
        // Assert that email notification job has been dispatched
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(2010, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(2010, $transaction->amount);
        $this->assertSame(
            "Auto-payment transaction {$payment->id} using Mastercard (**** **** **** 6787)",
            $transaction->description
        );

        Bus::assertDispatchedTimes(PaymentJob::class, 1);
        Bus::assertDispatched(PaymentJob::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

        // Expect no payment if the mandate is disabled
        $wallet->setSetting('mandate_disabled', 1);
        $result = $wallet->topUp();
        $this->assertFalse($result);
        $this->assertCount(2, $wallet->payments()->get());

        // Expect no payment if balance is ok
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = 1000;
        $wallet->save();
        $result = $wallet->topUp();
        $this->assertFalse($result);
        $this->assertCount(2, $wallet->payments()->get());

        // Expect no payment if the top-up amount is not enough
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = -2050;
        $wallet->save();
        $result = $wallet->topUp();
        $this->assertFalse($result);
        $this->assertCount(2, $wallet->payments()->get());

        Bus::assertDispatchedTimes(PaymentMandateDisabledJob::class, 1);
        Bus::assertDispatched(PaymentMandateDisabledJob::class, function ($job) use ($wallet) {
            $job_wallet = $this->getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        // Expect no payment if there's no mandate
        $wallet->setSetting('mollie_mandate_id', null);
        $wallet->balance = 0;
        $wallet->save();
        $result = $wallet->topUp();
        $this->assertFalse($result);
        $this->assertCount(2, $wallet->payments()->get());

        Bus::assertDispatchedTimes(PaymentMandateDisabledJob::class, 1);

        // Test webhook for recurring payments

        $wallet->transactions()->delete();

        Bus::fake();

        $payment->refresh();
        $payment->status = Payment::STATUS_OPEN;
        $payment->save();

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "paid",
            // Status is not enough, paidAt is used to distinguish the state
            "paidAt" => date('c'),
            "mode" => "test",
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // a request to the Mollie payments API. We cannot force Mollie
        // to make the payment status change.
        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(2010, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()
            ->where('type', Transaction::WALLET_CREDIT)->get()->last();

        $this->assertSame(2010, $transaction->amount);
        $this->assertSame(
            "Auto-payment transaction {$payment->id} using Mollie",
            $transaction->description
        );

        // Assert that email notification job has been dispatched
        Bus::assertDispatchedTimes(PaymentJob::class, 1);
        Bus::assertDispatched(PaymentJob::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

        Bus::fake();

        // Test for payment failure
        $payment->refresh();
        $payment->status = Payment::STATUS_OPEN;
        $payment->save();

        $wallet->setSetting('mollie_mandate_id', 'xxx');
        $wallet->setSetting('mandate_disabled', null);

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "failed",
            "mode" => "test",
        ];

        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response, 200, []),
        ]);

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $wallet->refresh();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(2010, $wallet->balance);
        $this->assertTrue(!empty($wallet->getSetting('mandate_disabled')));

        // Assert that email notification job has been dispatched
        Bus::assertDispatchedTimes(PaymentJob::class, 1);
        Bus::assertDispatched(PaymentJob::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });
    }

    /**
     * Test refund/chargeback handling by the webhook
     *
     * @group mollie
     */
    public function testRefundAndChargeback(): void
    {
        Bus::fake();

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();
        $wallet->transactions()->delete();

        // Create a paid payment
        $payment = Payment::create([
            'id' => 'tr_123456',
            'status' => Payment::STATUS_PAID,
            'amount' => 123,
            'credit_amount' => 123,
            'currency_amount' => 123,
            'currency' => 'EUR',
            'type' => Payment::TYPE_ONEOFF,
            'wallet_id' => $wallet->id,
            'provider' => 'mollie',
            'description' => 'test',
        ]);

        // Test handling a refund by the webhook

        $mollie_response1 = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "paid",
            // Status is not enough, paidAt is used to distinguish the state
            "paidAt" => date('c'),
            "mode" => "test",
            "_links" => [
                "refunds" => [
                    "href" => "https://api.mollie.com/v2/payments/{$payment->id}/refunds",
                    "type" => "application/hal+json",
                ],
            ],
        ];

        $mollie_response2 = [
            "count" => 1,
            "_links" => [],
            "_embedded" => [
                "refunds" => [
                    [
                        "resource" => "refund",
                        "id" => "re_123456",
                        "status" => RefundStatus::STATUS_REFUNDED,
                        "paymentId" => $payment->id,
                        "description" => "refund desc",
                        "amount" => [
                            "currency" => "EUR",
                            "value" => "1.01",
                        ],
                    ],
                ],
            ],
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // requests to the Mollie payments API.
        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response1, 200, []),
            self::API_URL . "/payments/{$payment->id}/refunds" => Http::response($mollie_response2, 200, []),
        ]);

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $wallet->refresh();

        $this->assertSame(-101, $wallet->balance);

        $transactions = $wallet->transactions()->where('type', Transaction::WALLET_REFUND)->get();

        $this->assertCount(1, $transactions);
        $this->assertSame(-101, $transactions[0]->amount);
        $this->assertSame(Transaction::WALLET_REFUND, $transactions[0]->type);
        $this->assertSame("refund desc", $transactions[0]->description);

        $payments = $wallet->payments()->where('id', 're_123456')->get();

        $this->assertCount(1, $payments);
        $this->assertSame(-101, $payments[0]->amount);
        $this->assertSame(-101, $payments[0]->currency_amount);
        $this->assertSame(Payment::STATUS_PAID, $payments[0]->status);
        $this->assertSame(Payment::TYPE_REFUND, $payments[0]->type);
        $this->assertSame("mollie", $payments[0]->provider);
        $this->assertSame("refund desc", $payments[0]->description);

        // Test handling a chargeback by the webhook

        $mollie_response1["_links"] = [
            "chargebacks" => [
                "href" => "https://api.mollie.com/v2/payments/{$payment->id}/chargebacks",
                "type" => "application/hal+json",
            ],
        ];

        $mollie_response2 = [
            "count" => 1,
            "_links" => [],
            "_embedded" => [
                "chargebacks" => [
                    [
                        "resource" => "chargeback",
                        "id" => "chb_123456",
                        "paymentId" => $payment->id,
                        "amount" => [
                            "currency" => "EUR",
                            "value" => "0.15",
                        ],
                    ],
                ],
            ],
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // requests to the Mollie payments API.
        Http::fakeClear()->fake([
            self::API_URL . "/payments/{$payment->id}" => Http::response($mollie_response1, 200, []),
            self::API_URL . "/payments/{$payment->id}/chargebacks" => Http::response($mollie_response2, 200, []),
        ]);

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $wallet->refresh();

        $this->assertSame(-116, $wallet->balance);

        $transactions = $wallet->transactions()->where('type', Transaction::WALLET_CHARGEBACK)->get();

        $this->assertCount(1, $transactions);
        $this->assertSame(-15, $transactions[0]->amount);
        $this->assertSame(Transaction::WALLET_CHARGEBACK, $transactions[0]->type);
        $this->assertSame('', $transactions[0]->description);

        $payments = $wallet->payments()->where('id', 'chb_123456')->get();

        $this->assertCount(1, $payments);
        $this->assertSame(-15, $payments[0]->amount);
        $this->assertSame(Payment::STATUS_PAID, $payments[0]->status);
        $this->assertSame(Payment::TYPE_CHARGEBACK, $payments[0]->type);
        $this->assertSame("mollie", $payments[0]->provider);
        $this->assertSame('', $payments[0]->description);

        Bus::assertNotDispatched(PaymentJob::class);
    }

    /**
     * Test listing a pending payment
     *
     * @group mollie
     */
    public function testListingPayments(): void
    {
        Bus::fake();

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();

        // Empty response
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertFalse($json['hasMore']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertFalse($json['hasPending']);

        // Successful payment
        $post = ['amount' => '12.34', 'currency' => 'EUR', 'methodId' => 'creditcard'];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        // A response
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertFalse($json['hasMore']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(Payment::STATUS_OPEN, $json['list'][0]['status']);
        $this->assertSame('EUR', $json['list'][0]['currency']);
        $this->assertSame(Payment::TYPE_ONEOFF, $json['list'][0]['type']);
        $this->assertSame(1234, $json['list'][0]['amount']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertTrue($json['hasPending']);

        // Set the payment to paid
        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];

        $payment->status = Payment::STATUS_PAID;
        $payment->save();

        // They payment should be gone from the pending list now
        $response = $this->actingAs($user)->get("api/v4/payments/pending");
        $json = $response->json();
        $this->assertSame('success', $json['status']);
        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($user)->get("api/v4/payments/has-pending");
        $json = $response->json();
        $this->assertFalse($json['hasPending']);
    }

    /**
     * Test listing payment methods
     *
     * @group mollie
     */
    public function testListingPaymentMethods(): void
    {
        Bus::fake();

        $user = $this->getTestUser('euro@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->currency = 'EUR';
        $wallet->save();

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . Payment::TYPE_ONEOFF);
        $response->assertStatus(200);
        $json = $response->json();

        $hasCoinbase = !empty(\config('services.coinbase.key'));

        $this->assertCount(3 + (int) $hasCoinbase, $json);
        $this->assertSame('creditcard', $json[0]['id']);
        $this->assertSame('paypal', $json[1]['id']);
        $this->assertSame('banktransfer', $json[2]['id']);
        $this->assertSame('EUR', $json[0]['currency']);
        $this->assertSame('EUR', $json[1]['currency']);
        $this->assertSame('EUR', $json[2]['currency']);
        $this->assertSame(1, $json[0]['exchangeRate']);
        $this->assertSame(1, $json[1]['exchangeRate']);
        $this->assertSame(1, $json[2]['exchangeRate']);

        if ($hasCoinbase) {
            $this->assertSame('bitcoin', $json[3]['id']);
            $this->assertSame('BTC', $json[3]['currency']);
        }

        $response = $this->actingAs($user)->get('api/v4/payments/methods?type=' . Payment::TYPE_RECURRING);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('creditcard', $json[0]['id']);
        $this->assertSame('EUR', $json[0]['currency']);
    }
}
