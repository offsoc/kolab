<?php

namespace Tests\Feature\Controller\Admin;

use App\Discount;
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
