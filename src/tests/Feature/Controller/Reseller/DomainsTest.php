<?php

namespace Tests\Feature\Controller\Reseller;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\Tenant;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();

        $this->deleteTestDomain('domainscontroller.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestDomain('domainscontroller.com');

        parent::tearDown();
    }

    /**
     * Test domain confirm request
     */
    public function testConfirm(): void
    {
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        // THe end-point exists on the users controller, but not reseller's
        $response = $this->actingAs($reseller1)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(404);
    }

    /**
     * Test domains searching (/api/v4/domains)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/domains");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/domains");
        $response->assertStatus(403);

        // Search with no matches expected
        $response = $this->actingAs($reseller1)->get("api/v4/domains?search=abcd12.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by a domain name
        $response = $this->actingAs($reseller1)->get("api/v4/domains?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('kolab.org', $json['list'][0]['namespace']);

        // Search by owner
        $response = $this->actingAs($reseller1)->get("api/v4/domains?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('kolab.org', $json['list'][0]['namespace']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only domains assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');

        $response = $this->actingAs($reseller1)->get("api/v4/domains?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        // Test unauth access to other tenant's domains
        $response = $this->actingAs($reseller2)->get("api/v4/domains?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        $response = $this->actingAs($reseller2)->get("api/v4/domains?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
    }

    /**
     * Test fetching domain info
     */
    public function testShow(): void
    {
        $sku_domain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('test1@domainscontroller.com');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        Entitlement::create([
                'wallet_id' => $user->wallets()->first()->id,
                'sku_id' => $sku_domain->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
        ]);

        // Unauthorized access (user)
        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);

        // Unauthorized access (admin)
        $response = $this->actingAs($admin)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);

        // Unauthorized access (tenant != env-tenant)
        $response = $this->actingAs($reseller2)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(404);

        $response = $this->actingAs($reseller1)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($domain->id, $json['id']);
        $this->assertEquals($domain->namespace, $json['namespace']);
        $this->assertEquals($domain->status, $json['status']);
        $this->assertEquals($domain->type, $json['type']);
        // Note: Other properties are being tested in the user controller tests
    }

    /**
     * Test fetching domain status (GET /api/v4/domains/<domain-id>/status)
     */
    public function testStatus(): void
    {
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $domain = $this->getTestDomain('kolab.org');

        // This end-point does not exist for resellers
        $response = $this->actingAs($reseller1)->get("/api/v4/domains/{$domain->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test domain suspending (POST /api/v4/domains/<domain-id>/suspend)
     */
    public function testSuspend(): void
    {
        Queue::fake(); // disable jobs

        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        \config(['app.tenant_id' => $reseller2->tenant_id]);

        $domain = $this->getTestDomain('domainscontroller.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_EXTERNAL,
        ]);
        $user = $this->getTestUser('test@domainscontroller.com');

        // Test unauthorized access to the reseller API (user)
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(403);

        $this->assertFalse($domain->fresh()->isSuspended());

        // Test unauthorized access to the reseller API (admin)
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(403);

        $this->assertFalse($domain->fresh()->isSuspended());

        // Test unauthorized access to the reseller API (reseller in another tenant)
        $response = $this->actingAs($reseller1)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(404);

        $this->assertFalse($domain->fresh()->isSuspended());

        // Test suspending the domain
        $response = $this->actingAs($reseller2)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain suspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertTrue($domain->fresh()->isSuspended());
    }

    /**
     * Test user un-suspending (POST /api/v4/users/<user-id>/unsuspend)
     */
    public function testUnsuspend(): void
    {
        Queue::fake(); // disable jobs

        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        \config(['app.tenant_id' => $reseller2->tenant_id]);

        $domain = $this->getTestDomain('domainscontroller.com', [
            'status' => Domain::STATUS_NEW | Domain::STATUS_SUSPENDED,
            'type' => Domain::TYPE_EXTERNAL,
        ]);
        $user = $this->getTestUser('test@domainscontroller.com');

        // Test unauthorized access to reseller API (user)
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(403);

        $this->assertTrue($domain->fresh()->isSuspended());

        // Test unauthorized access to reseller API (admin)
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(403);

        $this->assertTrue($domain->fresh()->isSuspended());

        // Test unauthorized access to reseller API (another tenant)
        $response = $this->actingAs($reseller1)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(404);

        $this->assertTrue($domain->fresh()->isSuspended());

        // Test suspending the user
        $response = $this->actingAs($reseller2)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertFalse($domain->fresh()->isSuspended());
    }
}
