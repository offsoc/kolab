<?php

namespace Tests\Feature\Controller\Admin;

use App\Auth\SecondFactor;
use App\Sku;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('test@testsearch.com');
        $this->deleteTestDomain('testsearch.com');

        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('test@testsearch.com');
        $this->deleteTestDomain('testsearch.com');

        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);

        parent::tearDown();
    }

    /**
     * Test users searching (/api/v4/users)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/users");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($admin)->get("api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($admin)->get("api/v4/users?search=abcd1234efgh5678");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by domain
        $response = $this->actingAs($admin)->get("api/v4/users?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by user ID
        $response = $this->actingAs($admin)->get("api/v4/users?search={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (primary)
        $response = $this->actingAs($admin)->get("api/v4/users?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (alias)
        $response = $this->actingAs($admin)->get("api/v4/users?search=john.doe@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (external), expect two users in a result
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', 'john.doe.external@gmail.com');

        $response = $this->actingAs($admin)->get("api/v4/users?search=john.doe.external@gmail.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);

        $emails = array_column($json['list'], 'email');

        $this->assertContains($user->email, $emails);
        $this->assertContains($jack->email, $emails);

        // Search by owner
        $response = $this->actingAs($admin)->get("api/v4/users?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(4, $json['count']);
        $this->assertCount(4, $json['list']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only users assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($admin)->get("api/v4/users?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        // Deleted users/domains
        $domain = $this->getTestDomain('testsearch.com', ['type' => \App\Domain::TYPE_EXTERNAL]);
        $user = $this->getTestUser('test@testsearch.com');
        $plan = \App\Plan::where('title', 'group')->first();
        $user->assignPlan($plan, $domain);
        $user->setAliases(['alias@testsearch.com']);
        Queue::fake();
        $user->delete();

        $response = $this->actingAs($admin)->get("api/v4/users?search=test@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);

        $response = $this->actingAs($admin)->get("api/v4/users?search=alias@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);

        $response = $this->actingAs($admin)->get("api/v4/users?search=testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);
    }

    /**
     * Test reseting 2FA (POST /api/v4/users/<user-id>/reset2FA)
     */
    public function testReset2FA(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        $sku2fa = Sku::firstOrCreate(['title' => '2fa']);
        $user->assignSku($sku2fa);
        SecondFactor::seed('userscontrollertest1@userscontroller.com');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/users/{$user->id}/reset2FA", []);
        $response->assertStatus(403);

        $entitlements = $user->fresh()->entitlements()->where('sku_id', $sku2fa->id)->get();
        $this->assertCount(1, $entitlements);

        $sf = new SecondFactor($user);
        $this->assertCount(1, $sf->factors());

        // Test reseting 2FA
        $response = $this->actingAs($admin)->post("/api/v4/users/{$user->id}/reset2FA", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("2-Factor authentication reset successfully.", $json['message']);
        $this->assertCount(2, $json);

        $entitlements = $user->fresh()->entitlements()->where('sku_id', $sku2fa->id)->get();
        $this->assertCount(0, $entitlements);

        $sf = new SecondFactor($user);
        $this->assertCount(0, $sf->factors());
    }

    /**
     * Test user suspending (POST /api/v4/users/<user-id>/suspend)
     */
    public function testSuspend(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/users/{$user->id}/suspend", []);
        $response->assertStatus(403);

        $this->assertFalse($user->isSuspended());

        // Test suspending the user
        $response = $this->actingAs($admin)->post("/api/v4/users/{$user->id}/suspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("User suspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertTrue($user->fresh()->isSuspended());
    }

    /**
     * Test user un-suspending (POST /api/v4/users/<user-id>/unsuspend)
     */
    public function testUnsuspend(): void
    {
        Queue::fake(); // disable jobs

        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/users/{$user->id}/unsuspend", []);
        $response->assertStatus(403);

        $this->assertFalse($user->isSuspended());
        $user->suspend();
        $this->assertTrue($user->isSuspended());

        // Test suspending the user
        $response = $this->actingAs($admin)->post("/api/v4/users/{$user->id}/unsuspend", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("User unsuspended successfully.", $json['message']);
        $this->assertCount(2, $json);

        $this->assertFalse($user->fresh()->isSuspended());
    }

    /**
     * Test user update (PUT /api/v4/users/<user-id>)
     */
    public function testUpdate(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->put("/api/v4/users/{$user->id}", []);
        $response->assertStatus(403);

        // Test updatig the user data (empty data)
        $response = $this->actingAs($admin)->put("/api/v4/users/{$user->id}", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("User data updated successfully.", $json['message']);
        $this->assertCount(2, $json);

        // Test error handling
        $post = ['external_email' => 'aaa'];
        $response = $this->actingAs($admin)->put("/api/v4/users/{$user->id}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The external email must be a valid email address.", $json['errors']['external_email'][0]);
        $this->assertCount(2, $json);

        // Test real update
        $post = ['external_email' => 'modified@test.com'];
        $response = $this->actingAs($admin)->put("/api/v4/users/{$user->id}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("User data updated successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertSame('modified@test.com', $user->getSetting('external_email'));
    }
}
