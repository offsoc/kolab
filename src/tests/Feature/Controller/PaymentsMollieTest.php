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
use Tests\BrowserAddonTrait;
use Tests\MollieMocksTrait;

class PaymentsMollieTest extends TestCase
{
    use MollieMocksTrait;
    use BrowserAddonTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // All tests in this file use Mollie
        \config(['services.payment_provider' => 'mollie']);

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
     * @group mollie
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

        $user = $this->getTestUser('john@kolab.org');

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
        $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        // Test creating a mandate (valid input)
        $post = ['amount' => 20.10, 'balance' => 0];
        $response = $this->actingAs($user)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertRegExp('|^https://www.mollie.com|', $json['redirectUrl']);

        // Test fetching the mandate information
        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertEquals('Credit Card', $json['method']);
        $this->assertSame(true, $json['isPending']);
        $this->assertSame(false, $json['isValid']);
        $this->assertSame(false, $json['isDisabled']);

        $mandate_id = $json['id'];

        // We would have to invoke a browser to accept the "first payment" to make
        // the mandate validated/completed. Instead, we'll mock the mandate object.
        $mollie_response = [
            'resource' => 'mandate',
            'id' => $mandate_id,
            'status' => 'valid',
            'method' => 'creditcard',
            'details' => [
                'cardNumber' => '4242',
                'cardLabel' => 'Visa',
            ],
            'customerId' => 'cst_GMfxGPt7Gj',
            'createdAt' => '2020-04-28T11:09:47+00:00',
        ];

        $responseStack = $this->mockMollie();
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $wallet = $user->wallets()->first();
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

        Bus::fake();
        $wallet->setSetting('mandate_disabled', null);

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

        // Test updating a mandate (valid input)
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $post = ['amount' => 30.10, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame($mandate_id, $json['id']);
        $this->assertFalse($json['isDisabled']);

        $wallet = $user->wallets()->first();

        $this->assertEquals(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertEquals(1, $wallet->getSetting('mandate_balance'));

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
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $post = ['amount' => 30, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame($mandate_id, $json['id']);
        $this->assertFalse($json['isDisabled']);

        Bus::assertDispatchedTimes(\App\Jobs\WalletCharge::class, 1);
        Bus::assertDispatched(\App\Jobs\WalletCharge::class, function ($job) use ($wallet) {
            $job_wallet = $this->getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        $this->unmockMollie();

        // Delete mandate
        $response = $this->actingAs($user)->delete("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been removed.', $json['message']);

        // Confirm with Mollie the mandate does not exist
        $customer_id = $wallet->getSetting('mollie_id');
        $this->expectException(\Mollie\Api\Exceptions\ApiException::class);
        $this->expectExceptionMessageMatches('/410: Gone/');
        $mandate = mollie()->mandates()->getForId($customer_id, $mandate_id);

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

        $user = $this->getTestUser('john@kolab.org');

        $post = ['amount' => -1];
        $response = $this->actingAs($user)->post("api/v4/payments", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
        $this->assertSame("Minimum amount for a single payment is {$min}.", $json['errors']['amount']);

        $post = ['amount' => '12.34'];
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
        $this->assertSame(\config('app.name') . ' Payment', $payment->description);
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
        ];

        // We'll trigger the webhook with payment id and use mocking for
        // a request to the Mollie payments API. We cannot force Mollie
        // to make the payment status change.
        $responseStack = $this->mockMollie();
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()->where('type', Transaction::WALLET_CREDIT)->last();
        $this->assertSame(1234, $transaction->amount);
        $this->assertSame(
            "Payment transaction {$payment->id} using Mollie",
            $transaction->description
        );

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);

        // Verify "paid -> open -> paid" scenario, assert that balance didn't change
        $mollie_response['status'] = 'open';
        unset($mollie_response['paidAt']);
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        $mollie_response['status'] = 'paid';
        $mollie_response['paidAt'] = date('c');
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Test for payment failure
        Bus::fake();

        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
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
        $responseStack = $this->mockMollie();
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertEquals(1234, $wallet->fresh()->balance);

        // Assert that email notification job wasn't dispatched,
        // it is expected only for recurring payments
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 0);
    }

    /**
     * Test automatic payment charges
     *
     * @group mollie
     */
    public function testTopUp(): void
    {
        Bus::fake();

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Create a valid mandate first
        $this->createMandate($wallet, ['amount' => 20.10, 'balance' => 10]);

        // Expect a recurring payment as we have a valid mandate at this point
        $result = PaymentsController::topUpWallet($wallet);
        $this->assertTrue($result);

        // Check that the payments table contains a new record with proper amount
        // There should be two records, one for the first payment and another for
        // the recurring payment
        $this->assertCount(1, $wallet->payments()->get());
        $payment = $wallet->payments()->first();
        $this->assertSame(2010, $payment->amount);

        // In mollie we don't have to wait for a webhook, the response to
        // PaymentIntent already sets the status to 'paid', so we can test
        // immediately the balance update
        // Assert that email notification job has been dispatched
        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->status);
        $this->assertEquals(2010, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->where('type', Transaction::WALLET_CREDIT)->last();
        $this->assertSame(2010, $transaction->amount);
        $this->assertSame(
            "Auto-payment transaction {$payment->id} using Mastercard (**** **** **** 6787)",
            $transaction->description
        );

        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 1);
        Bus::assertDispatched(\App\Jobs\PaymentEmail::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

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

        // Test webhook for recurring payments

        $responseStack = $this->mockMollie();
        Bus::fake();

        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
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
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $post = ['id' => $payment->id];
        $response = $this->post("api/webhooks/payment/mollie", $post);
        $response->assertStatus(200);

        $this->assertSame(PaymentProvider::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(2010, $wallet->fresh()->balance);

        $transaction = $wallet->transactions()->where('type', Transaction::WALLET_CREDIT)->last();
        $this->assertSame(2010, $transaction->amount);
        $this->assertSame(
            "Auto-payment transaction {$payment->id} using Mollie",
            $transaction->description
        );

        // Assert that email notification job has been dispatched
        Bus::assertDispatchedTimes(\App\Jobs\PaymentEmail::class, 1);
        Bus::assertDispatched(\App\Jobs\PaymentEmail::class, function ($job) use ($payment) {
            $job_payment = $this->getObjectProperty($job, 'payment');
            return $job_payment->id === $payment->id;
        });

        Bus::fake();

        // Test for payment failure
        $payment->refresh();
        $payment->status = PaymentProvider::STATUS_OPEN;
        $payment->save();

        $wallet->setSetting('mollie_mandate_id', 'xxx');
        $wallet->setSetting('mandate_disabled', null);

        $mollie_response = [
            "resource" => "payment",
            "id" => $payment->id,
            "status" => "failed",
            "mode" => "test",
        ];

        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

        $response = $this->post("api/webhooks/payment/mollie", $post);
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

        $responseStack = $this->unmockMollie();
    }

    /**
     * Create Mollie's auto-payment mandate using our API and Chrome browser
     */
    protected function createMandate(Wallet $wallet, array $params)
    {
        // Use the API to create a first payment with a mandate
        $response = $this->actingAs($wallet->owner)->post("api/v4/payments/mandate", $params);
        $response->assertStatus(200);
        $json = $response->json();

        // There's no easy way to confirm a created mandate.
        // The only way seems to be to fire up Chrome on checkout page
        // and do actions with use of Dusk browser.
        $this->startBrowser()
            ->visit($json['redirectUrl'])
            ->click('input[value="paid"]')
            ->click('button.form__button');

        $this->stopBrowser();
    }
}
