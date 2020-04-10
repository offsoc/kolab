<?php

namespace Tests\Feature\Controller\Admin;

use App\Discount;
use Tests\TestCase;

class DiscountsTest extends TestCase
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
     * Test listing discounts (/api/v4/discounts)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/discounts");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/discounts");
        $response->assertStatus(200);

        $json = $response->json();

        $discount_test = Discount::where('code', 'TEST')->first();
        $discount_free = Discount::where('discount', 100)->first();

        $this->assertSame(3, $json['count']);
        $this->assertSame($discount_test->id, $json['list'][0]['id']);
        $this->assertSame($discount_test->discount, $json['list'][0]['discount']);
        $this->assertSame($discount_test->code, $json['list'][0]['code']);
        $this->assertSame($discount_test->description, $json['list'][0]['description']);
        $this->assertSame('10% - Test voucher [TEST]', $json['list'][0]['label']);

        $this->assertSame($discount_free->id, $json['list'][2]['id']);
        $this->assertSame($discount_free->discount, $json['list'][2]['discount']);
        $this->assertSame($discount_free->code, $json['list'][2]['code']);
        $this->assertSame($discount_free->description, $json['list'][2]['description']);
        $this->assertSame('100% - Free Account', $json['list'][2]['label']);
    }
}
