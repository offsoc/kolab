<?php

namespace Tests\Feature\Controller\Reseller;

use App\Http\Controllers\API\V4\Reseller\PaymentsController;
use App\Payment;
use App\Transaction;
use App\Wallet;
use App\WalletSetting;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\BrowserAddonTrait;

class PaymentsMollieTest extends TestCase
{
    use BrowserAddonTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!\config('services.mollie.key')) {
            $this->markTestSkipped('No MOLLIE_KEY');
        }

        // All tests in this file use Mollie
        \config(['services.payment_provider' => 'mollie']);

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
        $wallet = $reseller->wallets()->first();
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
        Transaction::where('object_id', $wallet->id)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if (\config('services.mollie.key')) {
            $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
            $wallet = $reseller->wallets()->first();
            Payment::where('wallet_id', $wallet->id)->delete();
            Wallet::where('id', $wallet->id)->update(['balance' => 0]);
            WalletSetting::where('wallet_id', $wallet->id)->delete();
            Transaction::where('object_id', $wallet->id)->delete();
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

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
        $wallet = $reseller->wallets()->first();
        $wallet->balance = -10;
        $wallet->save();

        // Test creating a mandate (valid input)
        $json = $this->createMollieMandate($wallet, ['amount' => 20.10, 'balance' => 0]);

        $mandate_id = $json['mandateId'];

        // Assert the proper payment amount has been used
        $payment = Payment::where('id', $json['id'])->first();

        $this->assertSame(2010, $payment->amount);
        $this->assertSame($wallet->id, $payment->wallet_id);
        $this->assertSame($reseller->tenant->title . " Auto-Payment Setup", $payment->description);
        $this->assertSame(Payment::TYPE_MANDATE, $payment->type);

        // Test fetching the mandate information
        $response = $this->actingAs($reseller)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertTrue(in_array($json['method'], ['Mastercard (**** **** **** 9399)', 'Credit Card']));
        $this->assertSame(false, $json['isPending']);
        $this->assertSame(true, $json['isValid']);
        $this->assertSame(false, $json['isDisabled']);

        $wallet = $reseller->wallets()->first();
        $wallet->setSetting('mandate_disabled', 1);

        $response = $this->actingAs($reseller)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertTrue(in_array($json['method'], ['Mastercard (**** **** **** 9399)', 'Credit Card']));
        $this->assertSame(false, $json['isPending']);
        $this->assertSame(true, $json['isValid']);
        $this->assertSame(true, $json['isDisabled']);

        Bus::fake();
        $wallet->setSetting('mandate_disabled', null);
        $wallet->balance = 1000;
        $wallet->save();

        // Test updating a mandate (valid input)
        $post = ['amount' => 30.10, 'balance' => 10];
        $response = $this->actingAs($reseller)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);
        $this->assertSame($mandate_id, $json['id']);
        $this->assertFalse($json['isDisabled']);

        $wallet->refresh();

        $this->assertEquals(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertEquals(10, $wallet->getSetting('mandate_balance'));

        Bus::assertDispatchedTimes(\App\Jobs\Wallet\ChargeJob::class, 0);

        // Delete mandate
        $response = $this->actingAs($reseller)->delete("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been removed.', $json['message']);
    }

    /**
     * Test creating a payment
     *
     * @group mollie
     */
    public function testStore(): void
    {
        Bus::fake();

        // Unauth access not allowed
        $response = $this->post("api/v4/payments", []);
        $response->assertStatus(401);

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));

        // Successful payment
        $post = ['amount' => '12.34', 'currency' => 'CHF', 'methodId' => 'creditcard'];
        $response = $this->actingAs($reseller)->post("api/v4/payments", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^https://www.mollie.com|', $json['redirectUrl']);
    }

    /**
     * Test listing a pending payment
     *
     * @group mollie
     */
    public function testListingPayments(): void
    {
        Bus::fake();

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));

        // Empty response
        $response = $this->actingAs($reseller)->get("api/v4/payments/pending");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($reseller)->get("api/v4/payments/has-pending");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(false, $json['hasPending']);
    }

    /**
     * Test listing payment methods
     *
     * @group mollie
     */
    public function testListingPaymentMethods(): void
    {
        Bus::fake();

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));

        $response = $this->actingAs($reseller)->get('api/v4/payments/methods?type=' . Payment::TYPE_ONEOFF);
        $response->assertStatus(200);
        $json = $response->json();

        $hasCoinbase = !empty(\config('services.coinbase.key'));

        $this->assertCount(3 + intval($hasCoinbase), $json);
        $this->assertSame('creditcard', $json[0]['id']);
        $this->assertSame('paypal', $json[1]['id']);
        $this->assertSame('banktransfer', $json[2]['id']);
        $this->assertSame('bitcoin', $json[3]['id']);
    }
}
