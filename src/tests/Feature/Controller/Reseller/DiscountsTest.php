<?php

namespace Tests\Feature\Controller\Reseller;

use App\Discount;
use App\Tenant;
use Tests\TestCase;

class DiscountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test listing discounts (GET /api/v4/users/{id}/discounts)
     */
    public function testUserDiscounts(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/discounts");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/users/{$user->id}/discounts");
        $response->assertStatus(403);

        // Reseller user, but different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/users/{$user->id}/discounts");
        $response->assertStatus(404);

        // Reseller
        $response = $this->actingAs($reseller1)->get("api/v4/users/{$user->id}/discounts");
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
        $this->assertSame('100% - Free Account [FREE]', $json['list'][2]['label']);

        // A user in another tenant's user
        $user = $this->getTestUser('user@sample-tenant.dev-local');

        $response = $this->actingAs($reseller1)->get("api/v4/users/{$user->id}/discounts");
        $response->assertStatus(404);

        $response = $this->actingAs($reseller2)->get("api/v4/users/{$user->id}/discounts");
        $response->assertStatus(200);

        $json = $response->json();

        $discount = Discount::withObjectTenantContext($user)->where('discount', 10)->first();

        $this->assertSame(1, $json['count']);
        $this->assertSame($discount->id, $json['list'][0]['id']);
        $this->assertSame($discount->discount, $json['list'][0]['discount']);
        $this->assertSame($discount->code, $json['list'][0]['code']);
        $this->assertSame($discount->description, $json['list'][0]['description']);
        $this->assertSame('10% - ' . $discount->description, $json['list'][0]['label']);
    }
}
