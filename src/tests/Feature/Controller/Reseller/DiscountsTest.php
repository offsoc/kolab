<?php

namespace Tests\Feature\Controller\Reseller;

use App\Discount;
use App\Tenant;
use Tests\TestCase;

class DiscountsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('test@reseller.com');

        $tenant = Tenant::where('title', 'Sample Tenant')->first();
        $tenant->discounts()->delete();

        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \config(['app.tenant_id' => 1]);

        $this->deleteTestUser('test@reseller.com');

        $tenant = Tenant::where('title', 'Sample Tenant')->first();
        $tenant->discounts()->delete();

        parent::tearDown();
    }

    /**
     * Test listing discounts (/api/v4/discounts)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@reseller.com');
        $tenant = Tenant::where('title', 'Sample Tenant')->first();
        $tenant2 = Tenant::where('title', 'Kolab Now')->first();
        $reseller2 = $this->getTestUser('test@reseller.com');
        $reseller2->tenant_id = $tenant2->id;
        $reseller2->role = 'reseller';
        $reseller2->save();

        $reseller2->tenant_id = $tenant2->id;
        $reseller2->role = 'reseller';
        $reseller2->save();

        \config(['app.tenant_id' => $tenant->id]);

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/discounts");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/discounts");
        $response->assertStatus(403);

        // Reseller user, but different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/discounts");
        $response->assertStatus(403);

        // Reseller (empty list)
        $response = $this->actingAs($reseller)->get("api/v4/discounts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);

        // Add some discounts
        $discount_test = Discount::create([
                'description' => 'Test reseller voucher',
                'code' => 'RESELLER-TEST',
                'discount' => 10,
                'active' => true,
        ]);

        $discount_free = Discount::create([
                'description' => 'Free account',
                'discount' => 100,
                'active' => true,
        ]);

        $discount_test->tenant_id = $tenant->id;
        $discount_test->save();
        $discount_free->tenant_id = $tenant->id;
        $discount_free->save();

        $response = $this->actingAs($reseller)->get("api/v4/discounts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertSame($discount_test->id, $json['list'][0]['id']);
        $this->assertSame($discount_test->discount, $json['list'][0]['discount']);
        $this->assertSame($discount_test->code, $json['list'][0]['code']);
        $this->assertSame($discount_test->description, $json['list'][0]['description']);
        $this->assertSame('10% - Test reseller voucher [RESELLER-TEST]', $json['list'][0]['label']);

        $this->assertSame($discount_free->id, $json['list'][1]['id']);
        $this->assertSame($discount_free->discount, $json['list'][1]['discount']);
        $this->assertSame($discount_free->code, $json['list'][1]['code']);
        $this->assertSame($discount_free->description, $json['list'][1]['description']);
        $this->assertSame('100% - Free account', $json['list'][1]['label']);
    }
}
