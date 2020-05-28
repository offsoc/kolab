<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Wallet;
use App\WalletSetting;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;
use Tests\MollieMocksTrait;

class PaymentsMollieTest extends TestCase
{
    use MollieMocksTrait;

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
        $john->setSetting('mollie_id', null);
        Payment::where('wallet_id', $wallet->id)->delete();
        Wallet::where('id', $wallet->id)->update(['balance' => 0]);
        WalletSetting::where('wallet_id', $wallet->id)->delete();
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
        WalletSetting::where('wallet_id', $wallet->id)->delete();

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

        $mandate_id = $json['id'];

        // We would have to invoke a browser to accept the "first payment" to make
        // the mandate validated/completed. Instead, we'll mock the mandate object.
        $mollie_response = [
            'resource' => 'mandate',
            'id' => $json['id'],
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

        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);
        $this->assertEquals('Visa (**** **** **** 4242)', $json['method']);
        $this->assertSame(false, $json['isPending']);
        $this->assertSame(true, $json['isValid']);

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
        $post = ['amount' => 30.10, 'balance' => 1];
        $response = $this->actingAs($user)->put("api/v4/payments/mandate", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The auto-payment has been updated.', $json['message']);

        $wallet = $user->wallets()->first();

        $this->assertEquals(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertEquals(1, $wallet->getSetting('mandate_balance'));

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

    /**
     * Test automatic payment charges
     *
     * @group mollie
     */
    public function testDirectCharge(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        // Expect false result, as there's no mandate
        $result = PaymentsController::directCharge($wallet, 1234);
        $this->assertFalse($result);

        // Problem with this is we need to have a valid mandate
        // And there's no easy way to confirm a created mandate.
        // The only way seems to be to fire up Chrome on checkout page
        // and do some actions with use of Dusk browser.

        $this->markTestIncomplete();
    }
}
