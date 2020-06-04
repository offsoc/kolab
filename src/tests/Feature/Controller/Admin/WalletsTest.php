<?php

namespace Tests\Feature\Controller\Admin;

use App\Discount;
use App\Transaction;
use Tests\TestCase;

class WalletsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test fetching a wallet (GET /api/v4/wallets/:id)
     *
     * @group stripe
     */
    public function testShow(): void
    {
        \config(['services.payment_provider' => 'stripe']);

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $wallet = $user->wallets()->first();

        // Make sure there's no stripe/mollie identifiers
        $wallet->setSetting('stripe_id', null);
        $wallet->setSetting('stripe_mandate_id', null);
        $wallet->setSetting('mollie_id', null);
        $wallet->setSetting('mollie_mandate_id', null);

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/wallets/{$wallet->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($wallet->id, $json['id']);
        $this->assertSame('CHF', $json['currency']);
        $this->assertSame($wallet->balance, $json['balance']);
        $this->assertSame(0, $json['discount']);
        $this->assertTrue(empty($json['description']));
        $this->assertTrue(empty($json['discount_description']));
        $this->assertTrue(!empty($json['provider']));
        $this->assertTrue(!empty($json['providerLink']));
        $this->assertTrue(!empty($json['mandate']));
    }

    /**
     * Test awarding/penalizing a wallet (POST /api/v4/wallets/:id/one-off)
     */
    public function testOneOff(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $wallet = $user->wallets()->first();
        $balance = $wallet->balance;

        Transaction::where('object_id', $wallet->id)
            ->whereIn('type', [Transaction::WALLET_AWARD, Transaction::WALLET_PENALTY])
            ->delete();

        // Non-admin user
        $response = $this->actingAs($user)->post("api/v4/wallets/{$wallet->id}/one-off", []);
        $response->assertStatus(403);

        // Admin user - invalid input
        $post = ['amount' => 'aaaa'];
        $response = $this->actingAs($admin)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('The amount must be a number.', $json['errors']['amount'][0]);
        $this->assertSame('The description field is required.', $json['errors']['description'][0]);
        $this->assertCount(2, $json);
        $this->assertCount(2, $json['errors']);

        // Admin user - a valid bonus
        $post = ['amount' => '50', 'description' => 'A bonus'];
        $response = $this->actingAs($admin)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The bonus has been added to the wallet successfully.', $json['message']);
        $this->assertSame($balance += 5000, $json['balance']);
        $this->assertSame($balance, $wallet->fresh()->balance);

        $transaction = Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_AWARD)->first();

        $this->assertSame($post['description'], $transaction->description);
        $this->assertSame(5000, $transaction->amount);
        $this->assertSame($admin->email, $transaction->user_email);

        // Admin user - a valid penalty
        $post = ['amount' => '-40', 'description' => 'A penalty'];
        $response = $this->actingAs($admin)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The penalty has been added to the wallet successfully.', $json['message']);
        $this->assertSame($balance -= 4000, $json['balance']);
        $this->assertSame($balance, $wallet->fresh()->balance);

        $transaction = Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_PENALTY)->first();

        $this->assertSame($post['description'], $transaction->description);
        $this->assertSame(4000, $transaction->amount);
        $this->assertSame($admin->email, $transaction->user_email);
    }

    /**
     * Test updating a wallet (PUT /api/v4/wallets/:id)
     */
    public function testUpdate(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $wallet = $user->wallets()->first();
        $discount = Discount::where('code', 'TEST')->first();

        // Non-admin user
        $response = $this->actingAs($user)->put("api/v4/wallets/{$wallet->id}", []);
        $response->assertStatus(403);

        // Admin user - setting a discount
        $post = ['discount' => $discount->id];
        $response = $this->actingAs($admin)->put("api/v4/wallets/{$wallet->id}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('User wallet updated successfully.', $json['message']);
        $this->assertSame($wallet->id, $json['id']);
        $this->assertSame($discount->discount, $json['discount']);
        $this->assertSame($discount->id, $json['discount_id']);
        $this->assertSame($discount->description, $json['discount_description']);
        $this->assertSame($discount->id, $wallet->fresh()->discount->id);

        // Admin user - removing a discount
        $post = ['discount' => null];
        $response = $this->actingAs($admin)->put("api/v4/wallets/{$wallet->id}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('User wallet updated successfully.', $json['message']);
        $this->assertSame($wallet->id, $json['id']);
        $this->assertSame(null, $json['discount_id']);
        $this->assertTrue(empty($json['discount_description']));
        $this->assertSame(null, $wallet->fresh()->discount);
    }
}
