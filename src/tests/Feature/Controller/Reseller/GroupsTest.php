<?php

namespace Tests\Feature\Controller\Reseller;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GroupsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();

        $this->deleteTestGroup('group-test@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolab.org');

        parent::tearDown();
    }

    /**
     * Test groups searching (/api/v4/groups)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/groups");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/groups");
        $response->assertStatus(403);

        // Reseller from a different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/groups");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($reseller1)->get("api/v4/groups");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($reseller1)->get("api/v4/groups?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by email
        $response = $this->actingAs($reseller1)->get("api/v4/groups?search={$group->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($group->email, $json['list'][0]['email']);

        // Search by owner
        $response = $this->actingAs($reseller1)->get("api/v4/groups?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($group->email, $json['list'][0]['email']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only domains assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($reseller1)->get("api/v4/groups?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        // Test unauth access to other tenant's groups
        \config(['app.tenant_id' => 2]);

        $response = $this->actingAs($reseller2)->get("api/v4/groups?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        $response = $this->actingAs($reseller2)->get("api/v4/groups?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
    }

    /**
     * Test fetching group info
     */
    public function testShow(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('test1@domainscontroller.com');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Only resellers can access it
        $response = $this->actingAs($user)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($reseller2)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($reseller1)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($group->id, $json['id']);
        $this->assertEquals($group->email, $json['email']);
        $this->assertEquals($group->status, $json['status']);
    }

    /**
     * Test fetching group status (GET /api/v4/domains/<domain-id>/status)
     */
    public function testStatus(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // This end-point does not exist for admins
        $response = $this->actingAs($reseller1)->get("/api/v4/groups/{$group->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test group creating (POST /api/v4/groups)
     */
    public function testStore(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');

        // Test unauthorized access to reseller API
        $response = $this->actingAs($user)->post("/api/v4/groups", []);
        $response->assertStatus(403);

        // Reseller or admin can't create groups
        $response = $this->actingAs($admin)->post("/api/v4/groups", []);
        $response->assertStatus(403);

        $response = $this->actingAs($reseller1)->post("/api/v4/groups", []);
        $response->assertStatus(404);
    }

    /**
     * Test group suspending (POST /api/v4/groups/<group-id>/suspend)
     */
    public function testSuspend(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Test unauthorized access to reseller API
        $response = $this->actingAs($user)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(403);

        // Test unauthorized access to reseller API
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(403);

        // Test non-existing group ID
        $response = $this->actingAs($reseller1)->post("/api/v4/groups/abc/suspend", []);
        $response->assertStatus(404);

        $this->assertFalse($group->fresh()->isSuspended());

        // Test suspending the group
        $response = $this->actingAs($reseller1)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list suspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertTrue($group->fresh()->isSuspended());

        // Test unauth access to other tenant's groups
        \config(['app.tenant_id' => 2]);

        $response = $this->actingAs($reseller2)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(404);
    }

    /**
     * Test user un-suspending (POST /api/v4/users/<user-id>/unsuspend)
     */
    public function testUnsuspend(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@kolabnow.com');
        $reseller2 = $this->getTestUser('reseller@reseller.com');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());
        $group->status |= Group::STATUS_SUSPENDED;
        $group->save();

        // Test unauthorized access to reseller API
        $response = $this->actingAs($user)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(403);

        // Test unauthorized access to reseller API
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(403);

        // Invalid group ID
        $response = $this->actingAs($reseller1)->post("/api/v4/groups/abc/unsuspend", []);
        $response->assertStatus(404);

        $this->assertTrue($group->fresh()->isSuspended());

        // Test suspending the group
        $response = $this->actingAs($reseller1)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertFalse($group->fresh()->isSuspended());

        // Test unauth access to other tenant's groups
        \config(['app.tenant_id' => 2]);

        $response = $this->actingAs($reseller2)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(404);
    }
}