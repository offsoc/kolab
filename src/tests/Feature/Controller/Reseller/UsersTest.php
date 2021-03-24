<?php

namespace Tests\Feature\Controller\Reseller;

use App\Tenant;
use App\User;
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
        self::useResellerUrl();
        \config(['app.tenant_id' => 1]);

        // $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('test@testsearch.com');
        $this->deleteTestDomain('testsearch.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        // $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('test@testsearch.com');
        $this->deleteTestDomain('testsearch.com');

        \config(['app.tenant_id' => 1]);

        parent::tearDown();
    }

    /**
     * Test users searching (/api/v4/users)
     */
    public function testIndex(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@reseller.com');
        $reseller2 = $this->getTestUser('reseller@kolabnow.com');
        $tenant = Tenant::where('title', 'Sample Tenant')->first();

        \config(['app.tenant_id' => $tenant->id]);

        // Normal user
        $response = $this->actingAs($user)->get("api/v4/users");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/users");
        $response->assertStatus(403);

        // Reseller from another tenant
        $response = $this->actingAs($reseller2)->get("api/v4/users");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($reseller)->get("api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($reseller)->get("api/v4/users?search=abcd1234efgh5678");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by domain in another tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by user ID in another tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?search={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by email (primary) - existing user in another tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by owner - existing user in another tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Create a domain with some users in the Sample Tenant so we have anything to search for
        $domain = $this->getTestDomain('testsearch.com', ['type' => \App\Domain::TYPE_EXTERNAL]);
        $domain->tenant_id = $tenant->id;
        $domain->save();
        $user = $this->getTestUser('test@testsearch.com');
        $user->tenant_id = $tenant->id;
        $user->save();
        $plan = \App\Plan::where('title', 'group')->first();
        $user->assignPlan($plan, $domain);
        $user->setAliases(['alias@testsearch.com']);
        $user->setSetting('external_email', 'john.doe.external@gmail.com');

        // Search by domain
        $response = $this->actingAs($reseller)->get("api/v4/users?search=testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by user ID
        $response = $this->actingAs($reseller)->get("api/v4/users?search={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (primary) - existing user in reseller's tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?search=test@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (alias)
        $response = $this->actingAs($reseller)->get("api/v4/users?search=alias@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (external), there are two users with this email, but only one
        // in the reseller's tenant
        $response = $this->actingAs($reseller)->get("api/v4/users?search=john.doe.external@gmail.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by owner
        $response = $this->actingAs($reseller)->get("api/v4/users?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Deleted users/domains
        $user->delete();

        $response = $this->actingAs($reseller)->get("api/v4/users?search=test@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);

        $response = $this->actingAs($reseller)->get("api/v4/users?search=alias@testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);

        $response = $this->actingAs($reseller)->get("api/v4/users?search=testsearch.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);
        $this->assertTrue($json['list'][0]['isDeleted']);
    }

    /**
     * Test user update (PUT /api/v4/users/<user-id>)
     */
    public function testUpdate(): void
    {
        $this->markTestIncomplete();
/*
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
*/
    }
}
