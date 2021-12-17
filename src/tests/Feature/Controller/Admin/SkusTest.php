<?php

namespace Tests\Feature\Controller\Admin;

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
        self::useAdminUrl();

        Sku::where('title', 'test')->delete();

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Sku::where('title', 'test')->delete();

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();

        parent::tearDown();
    }

    /**
     * Test fetching SKUs list for a domain (GET /domains/<id>/skus)
     */
    public function testDomainSkus(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $domain = $this->getTestDOmain('kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/domains/{$domain->id}/skus");
        $response->assertStatus(401);

        // Non-admin access not allowed
        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/skus");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/domains/{$domain->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        // Note: Details are tested where we test API\V4\SkusController
    }

    /**
     * Test fetching SKUs list
     */
    public function testIndex(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        // Unauth access not allowed
        $response = $this->get("api/v4/skus");
        $response->assertStatus(401);

        // User access not allowed on admin API
        $response = $this->actingAs($user)->get("api/v4/skus");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(13, $json);

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
    }

    /**
     * Test fetching SKUs list for a user (GET /users/<id>/skus)
     */
    public function testUserSkus(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(401);

        // Non-admin access not allowed
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(6, $json);
        // Note: Details are tested where we test API\V4\SkusController
    }
}
