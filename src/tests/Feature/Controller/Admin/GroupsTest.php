<?php

namespace Tests\Feature\Controller\Admin;

use App\EventLog;
use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GroupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $this->deleteTestGroup('group-test@kolab.org');
    }

    protected function tearDown(): void
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
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/groups");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($admin)->get("api/v4/groups");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($admin)->get("api/v4/groups?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by email
        $response = $this->actingAs($admin)->get("api/v4/groups?search={$group->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($group->email, $json['list'][0]['email']);

        // Search by owner
        $response = $this->actingAs($admin)->get("api/v4/groups?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($group->email, $json['list'][0]['email']);
        $this->assertSame($group->name, $json['list'][0]['name']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only groups assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($admin)->get("api/v4/groups?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }

    /**
     * Test fetching group info
     */
    public function testShow(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Only admins can access it
        $response = $this->actingAs($user)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/groups/{$group->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($group->id, $json['id']);
        $this->assertSame($group->email, $json['email']);
        $this->assertSame($group->name, $json['name']);
        $this->assertSame($group->status, $json['status']);
    }

    /**
     * Test fetching group status (GET /api/v4/groups/<group-id>/status)
     */
    public function testStatus(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // This end-point does not exist for admins
        $response = $this->actingAs($admin)->get("/api/v4/groups/{$group->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test group creating (POST /api/v4/groups)
     */
    public function testStore(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/groups", []);
        $response->assertStatus(403);

        // Admin can't create groups
        $response = $this->actingAs($admin)->post("/api/v4/groups", []);
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
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(403);

        // Test non-existing group ID
        $response = $this->actingAs($admin)->post("/api/v4/groups/abc/suspend", []);
        $response->assertStatus(404);

        $this->assertFalse($group->fresh()->isSuspended());

        // Test suspending the group
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/suspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list suspended successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertTrue($group->fresh()->isSuspended());

        $group->unsuspend();
        EventLog::truncate();

        // Test suspending the group with a comment
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/suspend", ['comment' => 'Test']);
        $response->assertStatus(200);

        $where = [
            'object_id' => $group->id,
            'object_type' => Group::class,
            'type' => EventLog::TYPE_SUSPENDED,
            'comment' => 'Test',
        ];

        $this->assertSame(1, EventLog::where($where)->count());
        $this->assertTrue($group->fresh()->isSuspended());
    }

    /**
     * Test user un-suspending (POST /api/v4/users/<user-id>/unsuspend)
     */
    public function testUnsuspend(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($user->wallets->first());
        $group->status |= Group::STATUS_SUSPENDED;
        $group->save();

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(403);

        // Invalid group ID
        $response = $this->actingAs($admin)->post("/api/v4/groups/abc/unsuspend", []);
        $response->assertStatus(404);

        $this->assertTrue($group->fresh()->isSuspended());

        // Test suspending the group
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertFalse($group->fresh()->isSuspended());

        $group->unsuspend();
        EventLog::truncate();

        // Test unsuspending the group with a comment
        $response = $this->actingAs($admin)->post("/api/v4/groups/{$group->id}/unsuspend", ['comment' => 'Test']);
        $response->assertStatus(200);

        $where = [
            'object_id' => $group->id,
            'object_type' => Group::class,
            'type' => EventLog::TYPE_UNSUSPENDED,
            'comment' => 'Test',
        ];

        $this->assertSame(1, EventLog::where($where)->count());
        $this->assertFalse($group->fresh()->isSuspended());
    }
}
