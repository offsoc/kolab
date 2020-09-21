<?php

namespace Tests\Feature\Controller\Admin;

use App\Domain;
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
        self::useAdminUrl();

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
     * Test domains searching (/api/v4/domains)
     */
    public function testIndex(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Non-admin user
        $response = $this->actingAs($john)->get("api/v4/domains");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($admin)->get("api/v4/domains");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($admin)->get("api/v4/domains?search=abcd12.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by a domain name
        $response = $this->actingAs($admin)->get("api/v4/domains?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('kolab.org', $json['list'][0]['namespace']);

        // Search by owner
        $response = $this->actingAs($admin)->get("api/v4/domains?owner={$john->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('kolab.org', $json['list'][0]['namespace']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only domains assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($admin)->get("api/v4/domains?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }

    /**
     * Test domain suspending (POST /api/v4/domains/<domain-id>/suspend)
     */
    public function testSuspend(): void
    {
        Queue::fake(); // disable jobs

        $domain = $this->getTestDomain('domainscontroller.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_EXTERNAL,
        ]);
        $user = $this->getTestUser('test@domainscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(403);

        $this->assertFalse($domain->fresh()->isSuspended());

        // Test suspending the user
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/suspend", []);
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

        $domain = $this->getTestDomain('domainscontroller.com', [
            'status' => Domain::STATUS_NEW | Domain::STATUS_SUSPENDED,
            'type' => Domain::TYPE_EXTERNAL,
        ]);
        $user = $this->getTestUser('test@domainscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(403);

        $this->assertTrue($domain->fresh()->isSuspended());

        // Test suspending the user
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertFalse($domain->fresh()->isSuspended());
    }
}
