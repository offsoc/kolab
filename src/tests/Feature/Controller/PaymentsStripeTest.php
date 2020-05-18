<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Wallet;
use App\WalletSetting;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class PaymentsStripeTest extends TestCase
{
    use \Tests\StripeMocksTrait;

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
     * @group stripe
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
        $this->assertRegExp('|^cs_test_|', $json['id']);

        // Test fetching the mandate information
        $response = $this->actingAs($user)->get("api/v4/payments/mandate");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(20.10, $json['amount']);
        $this->assertEquals(0, $json['balance']);

        // We would have to invoke a browser to accept the "first payment" to make
        // the mandate validated/completed. Instead, we'll mock the mandate object.
        $setupIntent = '{
            "id": "AAA",
            "object": "setup_intent",
            "created": 123456789,
            "payment_method": "pm_YYY",
            "status": "succeeded",
            "usage": "off_session"
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


        $this->assertEquals(30.10, $wallet->getSetting('mandate_amount'));
        $this->assertEquals(1, $wallet->getSetting('mandate_balance'));

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
        $this->assertRegExp('|^cs_test_|', $json['id']);

        $wallet = $user->wallets()->first();
        $payments = Payment::where('wallet_id', $wallet->id)->get();

        $this->assertCount(1, $payments);
        $payment = $payments[0];
        $this->assertSame(1234, $payment->amount);
        $this->assertSame(\config('app.name') . ' Payment', $payment->description);
        $this->assertSame('open', $payment->status);
        $this->assertEquals(0, $wallet->balance);

        // TODO: Test the webhook
    }

    /**
     * Test automatic payment charges
     *
     * @group stripe
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
