<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\WalletsController;
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

        $this->deleteTestUser('wallets-controller@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test fetching wallet transactions
     */
    public function testTransactions(): void
    {
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $user->assignPackage($package_kolab);
        $john = $this->getTestUser('john@klab.org');
        $wallet = $user->wallets()->first();

        // Unauth access not allowed
        $response = $this->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(401);
        $response = $this->actingAs($john)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(403);

        // Expect empty list
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame([], $json['list']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(false, $json['hasMore']);

        // Create some sample transactions
        $transactions = $this->createTestTransactions($wallet);
        $transactions = array_reverse($transactions);
        $pages = array_chunk($transactions, 10 /* page size*/);

        // Get the first page
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(10, $json['count']);
        $this->assertSame(true, $json['hasMore']);
        $this->assertCount(10, $json['list']);
        foreach ($pages[0] as $idx => $transaction) {
            $this->assertSame($transaction->id, $json['list'][$idx]['id']);
            $this->assertSame($transaction->type, $json['list'][$idx]['type']);
            $this->assertSame($transaction->shortDescription(), $json['list'][$idx]['description']);
            $this->assertFalse($json['list'][$idx]['hasDetails']);
        }

        $search = null;

        // Get the second page
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions?page=2");
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
            $this->assertSame(
                $transaction->type == Transaction::WALLET_DEBIT,
                $json['list'][$idx]['hasDetails']
            );

            if ($transaction->type == Transaction::WALLET_DEBIT) {
                $search = $transaction->id;
            }
        }

        // Get a non-existing page
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions?page=3");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame(3, $json['page']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(0, $json['list']);

        // Sub-transaction searching
        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions?transaction=123");
        $response->assertStatus(404);

        $response = $this->actingAs($user)->get("api/v4/wallets/{$wallet->id}/transactions?transaction={$search}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(2, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(Transaction::ENTITLEMENT_BILLED, $json['list'][0]['type']);
        $this->assertSame(Transaction::ENTITLEMENT_BILLED, $json['list'][1]['type']);

        // Test that John gets 404 if he tries to access
        // someone else's transaction ID on his wallet's endpoint
        $wallet = $john->wallets()->first();
        $response = $this->actingAs($john)->get("api/v4/wallets/{$wallet->id}/transactions?transaction={$search}");
        $response->assertStatus(404);
    }
}
