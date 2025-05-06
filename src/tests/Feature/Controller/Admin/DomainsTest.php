<?php

namespace Tests\Feature\Controller\Admin;

use App\Domain;
use App\Entitlement;
use App\EventLog;
use App\Sku;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $this->deleteTestDomain('domainscontroller.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('test1@domainscontroller.com');
        $this->deleteTestDomain('domainscontroller.com');

        parent::tearDown();
    }

    /**
     * Test domains confirming (not implemented)
     */
    public function testConfirm(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $domain = $this->getTestDomain('kolab.org');

        // This end-point does not exist for admins
        $response = $this->actingAs($admin)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(404);
    }

    /**
     * Test deleting a domain (DELETE /api/v4/domains/<id>)
     */
    public function testDestroy(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $domain = $this->getTestDomain('kolab.org');

        // This end-point does not exist for admins
        $response = $this->actingAs($admin)->delete("api/v4/domains/{$domain->id}");
        $response->assertStatus(404);
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
     * Test fetching domain info
     */
    public function testShow(): void
    {
        $sku_domain = Sku::where('title', 'domain-hosting')->first();
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_EXTERNAL,
        ]);

        Entitlement::create([
            'wallet_id' => $user->wallets()->first()->id,
            'sku_id' => $sku_domain->id,
            'entitleable_id' => $domain->id,
            'entitleable_type' => Domain::class,
        ]);

        // Only admins can access it
        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($domain->id, $json['id']);
        $this->assertSame($domain->namespace, $json['namespace']);
        $this->assertSame($domain->status, $json['status']);
        $this->assertSame($domain->type, $json['type']);
        // Note: Other properties are being tested in the user controller tests
    }

    /**
     * Test fetching domain status (GET /api/v4/domains/<domain-id>/status)
     */
    public function testStatus(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $domain = $this->getTestDomain('kolab.org');

        // This end-point does not exist for admins
        $response = $this->actingAs($admin)->get("/api/v4/domains/{$domain->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test creeating a domain (POST /api/v4/domains)
     */
    public function testStore(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Admins can't create domains
        $response = $this->actingAs($admin)->post("api/v4/domains", []);
        $response->assertStatus(404);
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
        $user = $this->getTestUser('test1@domainscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(403);

        $this->assertFalse($domain->fresh()->isSuspended());

        // Test suspending the domain
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/suspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain suspended successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertTrue($domain->fresh()->isSuspended());

        $domain->unsuspend();
        EventLog::truncate();

        // Test suspending the domain with a comment
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/suspend", ['comment' => 'Test']);
        $response->assertStatus(200);

        $where = [
            'object_id' => $domain->id,
            'object_type' => Domain::class,
            'type' => EventLog::TYPE_SUSPENDED,
            'comment' => 'Test',
        ];

        $this->assertSame(1, EventLog::where($where)->count());
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
        $user = $this->getTestUser('test1@domainscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(403);

        $this->assertTrue($domain->fresh()->isSuspended());

        // Test suspending the domain
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertFalse($domain->fresh()->isSuspended());

        $domain->suspend();
        EventLog::truncate();

        // Test unsuspending the domain with a comment
        $response = $this->actingAs($admin)->post("/api/v4/domains/{$domain->id}/unsuspend", ['comment' => 'Test']);
        $response->assertStatus(200);

        $where = [
            'object_id' => $domain->id,
            'object_type' => Domain::class,
            'type' => EventLog::TYPE_UNSUSPENDED,
            'comment' => 'Test',
        ];

        $this->assertSame(1, EventLog::where($where)->count());
        $this->assertFalse($domain->fresh()->isSuspended());
    }
}
