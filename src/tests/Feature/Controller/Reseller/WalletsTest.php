<?php

namespace Tests\Feature\Controller\Reseller;

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
        self::useResellerUrl();
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
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $wallet = $user->wallets()->first();
        $wallet->discount_id = null;
        $wallet->save();

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
        $response->assertStatus(403);

        // Reseller from a different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/wallets/{$wallet->id}");
        $response->assertStatus(404);

        // Reseller
        $response = $this->actingAs($reseller1)->get("api/v4/wallets/{$wallet->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($wallet->id, $json['id']);
        $this->assertSame('CHF', $json['currency']);
        $this->assertSame($wallet->balance, $json['balance']);
        $this->assertSame(0, $json['discount']);
        $this->assertTrue(empty($json['description']));
        $this->assertTrue(empty($json['discount_description']));
        $this->assertTrue(!empty($json['provider']));
        $this->assertTrue(empty($json['providerLink']));
        $this->assertTrue(!empty($json['mandate']));

        // Reseller from a different tenant
        $user2 = $this->getTestUser('user@sample-tenant.dev-local');
        $wallet2 = $user2->wallets()->first();
        $response = $this->actingAs($reseller2)->get("api/v4/wallets/{$wallet2->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($wallet2->id, $json['id']);
    }

    /**
     * Test awarding/penalizing a wallet (POST /api/v4/wallets/:id/one-off)
     */
    public function testOneOff(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $wallet = $user->wallets()->first();
        $reseller1_wallet = $reseller1->wallets()->first();
        $balance = $wallet->balance;
        $reseller1_balance = $reseller1_wallet->balance;

        Transaction::where('object_id', $wallet->id)
            ->whereIn('type', [Transaction::WALLET_AWARD, Transaction::WALLET_PENALTY])
            ->delete();
        Transaction::where('object_id', $reseller1_wallet->id)->delete();

        // Non-admin user
        $response = $this->actingAs($user)->post("api/v4/wallets/{$wallet->id}/one-off", []);
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->post("api/v4/wallets/{$wallet->id}/one-off", []);
        $response->assertStatus(403);

        // Reseller from a different tenant
        $response = $this->actingAs($reseller2)->post("api/v4/wallets/{$wallet->id}/one-off", []);
        $response->assertStatus(404);

        // Admin user - invalid input
        $post = ['amount' => 'aaaa'];
        $response = $this->actingAs($reseller1)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('The amount must be a number.', $json['errors']['amount'][0]);
        $this->assertSame('The description field is required.', $json['errors']['description'][0]);
        $this->assertCount(2, $json);
        $this->assertCount(2, $json['errors']);

        // A valid bonus
        $post = ['amount' => '50', 'description' => 'A bonus'];
        $response = $this->actingAs($reseller1)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The bonus has been added to the wallet successfully.', $json['message']);
        $this->assertSame($balance += 5000, $json['balance']);
        $this->assertSame($balance, $wallet->fresh()->balance);
        $this->assertSame($reseller1_balance -= 5000, $reseller1_wallet->fresh()->balance);

        $transaction = Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_AWARD)->first();

        $this->assertSame($post['description'], $transaction->description);
        $this->assertSame(5000, $transaction->amount);
        $this->assertSame($reseller1->email, $transaction->user_email);

        $transaction = Transaction::where('object_id', $reseller1_wallet->id)
            ->where('type', Transaction::WALLET_DEBIT)->first();

        $this->assertSame("Awarded user {$user->email}", $transaction->description);
        $this->assertSame(-5000, $transaction->amount);
        $this->assertSame($reseller1->email, $transaction->user_email);

        // A valid penalty
        $post = ['amount' => '-40', 'description' => 'A penalty'];
        $response = $this->actingAs($reseller1)->post("api/v4/wallets/{$wallet->id}/one-off", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('The penalty has been added to the wallet successfully.', $json['message']);
        $this->assertSame($balance -= 4000, $json['balance']);
        $this->assertSame($balance, $wallet->fresh()->balance);
        $this->assertSame($reseller1_balance += 4000, $reseller1_wallet->fresh()->balance);

        $transaction = Transaction::where('object_id', $wallet->id)
            ->where('type', Transaction::WALLET_PENALTY)->first();

        $this->assertSame($post['description'], $transaction->description);
        $this->assertSame(-4000, $transaction->amount);
        $this->assertSame($reseller1->email, $transaction->user_email);

        $transaction = Transaction::where('object_id', $reseller1_wallet->id)
            ->where('type', Transaction::WALLET_CREDIT)->first();

        $this->assertSame("Penalized user {$user->email}", $transaction->description);
        $this->assertSame(4000, $transaction->amount);
        $this->assertSame($reseller1->email, $transaction->user_email);
    }

    /**
     * Test fetching wallet transactions (GET /api/v4/wallets/:id/transactions)
     */
    public function testTransactions(): void
    {
        // Note: Here we're testing only that the end-point works,
        // and admin can get the transaction log, response details
        // are tested in Feature/Controller/WalletsTest.php

        $this->deleteTestUser('wallets-controller@kolabnow.com');
        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $wallet = $user->wallets()->first();
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        // Non-admin
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(403);

        // Admin
        $response = $this->actingAs($admin)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(403);

        // Reseller from a different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(404);

        // Create some sample transactions
        $transactions = $this->createTestTransactions($wallet);
        $transactions = array_reverse($transactions);
        $pages = array_chunk($transactions, 10 /* page size*/);

        // Get the 2nd page
        $response = $this->actingAs($reseller1)->get("api/v4/wallets/{$wallet->id}/transactions?page=2");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame(2, $json['page']);
        $this->assertSame(2, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(2, $json['list']);
        foreach ($pages[1] as $idx => $transaction) {
            $this->assertSame($transaction->id, $json['list'][$idx]['id']);
            $this->assertSame($transaction->type, $json['list'][$idx]['type']);
            $this->assertSame($transaction->shortDescription(), $json['list'][$idx]['description']);
            $this->assertFalse($json['list'][$idx]['hasDetails']);
        }

        // The 'user' key is set only on the admin/reseller end-point
        // FIXME: Should we hide this for resellers?
        $this->assertSame('jeroen@jeroen.jeroen', $json['list'][1]['user']);

        // Test another tenant
        $user2 = $this->getTestUser('user@sample-tenant.dev-local');
        $wallet2 = $user2->wallets()->first();

        $response = $this->actingAs($reseller2)->get("api/v4/wallets/{$wallet2->id}/transactions");
        $response->assertStatus(200);
    }

    /**
     * Test updating a wallet (PUT /api/v4/wallets/:id)
     */
    public function testUpdate(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $discount = Discount::where('code', 'TEST')->first();

        // Non-admin user
        $response = $this->actingAs($user)->put("api/v4/wallets/{$wallet->id}", []);
        $response->assertStatus(403);

        // Admin
        $response = $this->actingAs($admin)->put("api/v4/wallets/{$wallet->id}", []);
        $response->assertStatus(403);

        // Reseller from another tenant
        $response = $this->actingAs($reseller2)->put("api/v4/wallets/{$wallet->id}", []);
        $response->assertStatus(404);

        // Setting a discount
        $post = ['discount' => $discount->id];
        $response = $this->actingAs($reseller1)->put("api/v4/wallets/{$wallet->id}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('User wallet updated successfully.', $json['message']);
        $this->assertSame($wallet->id, $json['id']);
        $this->assertSame($discount->discount, $json['discount']);
        $this->assertSame($discount->id, $json['discount_id']);
        $this->assertSame($discount->description, $json['discount_description']);
        $this->assertSame($discount->id, $wallet->fresh()->discount->id);

        // Removing a discount
        $post = ['discount' => null];
        $response = $this->actingAs($reseller1)->put("api/v4/wallets/{$wallet->id}", $post);
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
