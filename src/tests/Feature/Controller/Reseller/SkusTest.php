<?php

namespace Tests\Feature\Controller\Reseller;

use App\Sku;
use Tests\TestCase;

class SkusTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();

        \config(['app.tenant_id' => 1]);

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \config(['app.tenant_id' => 1]);

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();

        parent::tearDown();
    }

    /**
     * Test fetching SKUs list
     */
    public function testIndex(): void
    {
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $sku = Sku::where('title', 'mailbox')->first();

        // Unauth access not allowed
        $response = $this->get("api/v4/skus");
        $response->assertStatus(401);

        // User access not allowed on admin API
        $response = $this->actingAs($user)->get("api/v4/skus");
        $response->assertStatus(403);

        // Admin access not allowed
        $response = $this->actingAs($admin)->get("api/v4/skus");
        $response->assertStatus(403);

        $response = $this->actingAs($reseller1)->get("api/v4/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(8, $json);

        $this->assertSame(100, $json[0]['prio']);
        $this->assertSame($sku->id, $json[0]['id']);
        $this->assertSame($sku->title, $json[0]['title']);
        $this->assertSame($sku->name, $json[0]['name']);
        $this->assertSame($sku->description, $json[0]['description']);
        $this->assertSame($sku->cost, $json[0]['cost']);
        $this->assertSame($sku->units_free, $json[0]['units_free']);
        $this->assertSame($sku->period, $json[0]['period']);
        $this->assertSame($sku->active, $json[0]['active']);
        $this->assertSame('user', $json[0]['type']);
        $this->assertSame('mailbox', $json[0]['handler']);

        // TODO: Test limiting SKUs to the tenant's SKUs
    }

    /**
     * Test fetching SKUs list for a user (GET /users/<id>/skus)
     */
    public function testUserSkus(): void
    {
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(401);

        // User access not allowed
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(403);

        // Admin access not allowed
        $response = $this->actingAs($admin)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(403);

        // Reseller from another tenant not allowed
        $response = $this->actingAs($reseller2)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(403);

        // Reseller access
        $response = $this->actingAs($reseller1)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(8, $json);
        // Note: Details are tested where we test API\V4\SkusController

        // Reseller from another tenant not allowed
        \config(['app.tenant_id' => 2]);
        $response = $this->actingAs($reseller2)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(404);
    }
}
