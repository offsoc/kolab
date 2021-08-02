<?php

namespace Tests\Feature\Controller\Reseller;

use App\Http\Controllers\API\V4\Reseller\PaymentsController;
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
        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
        $wallet = $reseller->wallets()->first();
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
        Transaction::where('object_id', $wallet->id)->delete();

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

        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
        $wallet = $reseller->wallets()->first();
        $wallet->balance = -10;
        $wallet->save();

        // Test creating a mandate (valid input)
        $post = ['amount' => 20.10, 'balance' => 0];
        $response = $this->actingAs($reseller)->post("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^https://www.mollie.com|', $json['redirectUrl']);

        // Assert the proper payment amount has been used
        $payment = Payment::where('id', $json['id'])->first();

        $this->assertSame(2010, $payment->amount);
        $this->assertSame($wallet->id, $payment->wallet_id);
        $this->assertSame(\config('app.name') . " Auto-Payment Setup", $payment->description);
        $this->assertSame(PaymentProvider::TYPE_MANDATE, $payment->type);

        // Test fetching the mandate information
        $response = $this->actingAs($reseller)->get("api/v4/payments/mandate");
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

        $wallet = $reseller->wallets()->first();
        $wallet->setSetting('mandate_disabled', 1);

        $response = $this->actingAs($reseller)->get("api/v4/payments/mandate");
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
        $wallet->balance = 1000;
        $wallet->save();

        // Test updating a mandate (valid input)
        $responseStack->append(new Response(200, [], json_encode($mollie_response)));

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

        Bus::assertDispatchedTimes(\App\Jobs\WalletCharge::class, 0);

        $this->unmockMollie();

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

        $response = $this->actingAs($reseller)->get('api/v4/payments/methods?type=' . PaymentProvider::TYPE_ONEOFF);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('creditcard', $json[0]['id']);
        $this->assertSame('paypal', $json[1]['id']);
    }
}
