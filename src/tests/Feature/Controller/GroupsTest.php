<?php

namespace Tests\Feature\Controller;

use App\Group;
use App\Http\Controllers\API\V4\GroupsController;
use Carbon\Carbon;
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
     * Test group deleting (DELETE /api/v4/groups/<id>)
     */
    public function testDestroy(): void
    {
        // First create some groups to delete
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test unauth access
        $response = $this->delete("api/v4/groups/{$group->id}");
        $response->assertStatus(401);

        // Test non-existing group
        $response = $this->actingAs($john)->delete("api/v4/groups/abc");
        $response->assertStatus(404);

        // Test access to other user's group
        $response = $this->actingAs($jack)->delete("api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test removing a group
        $response = $this->actingAs($john)->delete("api/v4/groups/{$group->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals("Distribution list deleted successfully.", $json['message']);
    }

    /**
     * Test groups listing (GET /api/v4/groups)
     */
    public function testIndex(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test unauth access
        $response = $this->get("api/v4/groups");
        $response->assertStatus(401);

        // Test a user with no groups
        $response = $this->actingAs($jack)->get("/api/v4/groups");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(0, $json);

        // Test a user with a single group
        $response = $this->actingAs($john)->get("/api/v4/groups");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame($group->id, $json[0]['id']);
        $this->assertSame($group->email, $json[0]['email']);
        $this->assertArrayHasKey('isDeleted', $json[0]);
        $this->assertArrayHasKey('isSuspended', $json[0]);
        $this->assertArrayHasKey('isActive', $json[0]);
        $this->assertArrayHasKey('isLdapReady', $json[0]);

        // Test that another wallet controller has access to groups
        $response = $this->actingAs($ned)->get("/api/v4/groups");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame($group->email, $json[0]['email']);
    }

    /**
     * Test fetching group data/profile (GET /api/v4/groups/<group-id>)
     */
    public function testShow(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test unauthorized access to a profile of other user
        $response = $this->get("/api/v4/groups/{$group->id}");
        $response->assertStatus(401);

        // Test unauthorized access to a group of another user
        $response = $this->actingAs($jack)->get("/api/v4/groups/{$group->id}");
        $response->assertStatus(403);

        // John: Group owner - non-existing group
        $response = $this->actingAs($john)->get("/api/v4/groups/abc");
        $response->assertStatus(404);

        // John: Group owner
        $response = $this->actingAs($john)->get("/api/v4/groups/{$group->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($group->id, $json['id']);
        $this->assertSame($group->email, $json['email']);
        $this->assertSame($group->members, $json['members']);
        $this->assertTrue(!empty($json['statusInfo']));
        $this->assertArrayHasKey('isDeleted', $json);
        $this->assertArrayHasKey('isSuspended', $json);
        $this->assertArrayHasKey('isActive', $json);
        $this->assertArrayHasKey('isLdapReady', $json);
    }

    /**
     * Test fetching group status (GET /api/v4/groups/<group-id>/status)
     * and forcing setup process update (?refresh=1)
     */
    public function testStatus(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test unauthorized access
        $response = $this->get("/api/v4/groups/abc/status");
        $response->assertStatus(401);

        // Test unauthorized access
        $response = $this->actingAs($jack)->get("/api/v4/groups/{$group->id}/status");
        $response->assertStatus(403);

        $group->status = Group::STATUS_NEW | Group::STATUS_ACTIVE;
        $group->save();

        // Get group status
        $response = $this->actingAs($john)->get("/api/v4/groups/{$group->id}/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse($json['isLdapReady']);
        $this->assertFalse($json['isReady']);
        $this->assertFalse($json['isSuspended']);
        $this->assertTrue($json['isActive']);
        $this->assertFalse($json['isDeleted']);
        $this->assertCount(6, $json['process']);
        $this->assertSame('distlist-new', $json['process'][0]['label']);
        $this->assertSame(true, $json['process'][0]['state']);
        $this->assertSame('distlist-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(false, $json['process'][1]['state']);
        $this->assertTrue(empty($json['status']));
        $this->assertTrue(empty($json['message']));

        // Make sure the domain is confirmed (other test might unset that status)
        $domain = $this->getTestDomain('kolab.org');
        $domain->status |= \App\Domain::STATUS_CONFIRMED;
        $domain->save();

        // Now "reboot" the process and  the group
        $response = $this->actingAs($john)->get("/api/v4/groups/{$group->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isLdapReady']);
        $this->assertTrue($json['isReady']);
        $this->assertCount(6, $json['process']);
        $this->assertSame('distlist-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(true, $json['process'][1]['state']);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Setup process finished successfully.', $json['message']);

        // Test a case when a domain is not ready
        $domain->status ^= \App\Domain::STATUS_CONFIRMED;
        $domain->save();

        $response = $this->actingAs($john)->get("/api/v4/groups/{$group->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isLdapReady']);
        $this->assertTrue($json['isReady']);
        $this->assertCount(6, $json['process']);
        $this->assertSame('distlist-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(true, $json['process'][1]['state']);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Setup process finished successfully.', $json['message']);
    }

    /**
     * Test GroupsController::statusInfo()
     */
    public function testStatusInfo(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());
        $group->status = Group::STATUS_NEW | Group::STATUS_ACTIVE;
        $group->save();

        $result = GroupsController::statusInfo($group);

        $this->assertFalse($result['isReady']);
        $this->assertCount(6, $result['process']);
        $this->assertSame('distlist-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('distlist-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(false, $result['process'][1]['state']);
        $this->assertSame('running', $result['processState']);

        $group->created_at = Carbon::now()->subSeconds(181);
        $group->save();

        $result = GroupsController::statusInfo($group);

        $this->assertSame('failed', $result['processState']);

        $group->status |= Group::STATUS_LDAP_READY;
        $group->save();

        $result = GroupsController::statusInfo($group);

        $this->assertTrue($result['isReady']);
        $this->assertCount(6, $result['process']);
        $this->assertSame('distlist-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('distlist-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][2]['state']);
        $this->assertSame('done', $result['processState']);
    }

    /**
     * Test group creation (POST /api/v4/groups)
     */
    public function testStore(): void
    {
        Queue::fake();

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');

        // Test unauth request
        $response = $this->post("/api/v4/groups", []);
        $response->assertStatus(401);

        // Test non-controller user
        $response = $this->actingAs($jack)->post("/api/v4/groups", []);
        $response->assertStatus(403);

        // Test empty request
        $response = $this->actingAs($john)->post("/api/v4/groups", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The email field is required.", $json['errors']['email']);
        $this->assertCount(2, $json);

        // Test missing members
        $post = ['email' => 'group-test@kolab.org'];
        $response = $this->actingAs($john)->post("/api/v4/groups", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertSame("At least one recipient is required.", $json['errors']['members']);
        $this->assertCount(2, $json);

        // Test invalid email
        $post = ['email' => 'invalid'];
        $response = $this->actingAs($john)->post("/api/v4/groups", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The specified email is invalid.', $json['errors']['email']);

        // Test successful group creation
        $post = [
            'email' => 'group-test@kolab.org',
            'members' => ['test1@domain.tld', 'test2@domain.tld']
        ];

        $response = $this->actingAs($john)->post("/api/v4/groups", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list created successfully.", $json['message']);
        $this->assertCount(2, $json);

        $group = Group::where('email', 'group-test@kolab.org')->first();
        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame($post['email'], $group->email);
        $this->assertSame($post['members'], $group->members);
        $this->assertTrue($john->groups()->get()->contains($group));
    }

    /**
     * Test group update (PUT /api/v4/groups/<group-id>)
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test unauthorized update
        $response = $this->get("/api/v4/groups/{$group->id}", []);
        $response->assertStatus(401);

        // Test unauthorized update
        $response = $this->actingAs($jack)->get("/api/v4/groups/{$group->id}", []);
        $response->assertStatus(403);

        // Test updating - missing members
        $response = $this->actingAs($john)->put("/api/v4/groups/{$group->id}", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("At least one recipient is required.", $json['errors']['members']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['members' => ['test@domain.tld', 'invalid']];
        $response = $this->actingAs($john)->put("/api/v4/groups/{$group->id}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The specified email address is invalid.', $json['errors']['members'][1]);

        // Valid data - members changed
        $post = [
            'members' => ['member1@test.domain', 'member2@test.domain']
        ];

        $response = $this->actingAs($john)->put("/api/v4/groups/{$group->id}", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Distribution list updated successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertSame($group->fresh()->members, $post['members']);
    }

    /**
     * Group email address validation.
     */
    public function testValidateGroupEmail(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $group = $this->getTestGroup('group-test@kolab.org');

        // Invalid email
        $result = GroupsController::validateGroupEmail('', $john);
        $this->assertSame("The email field is required.", $result);

        $result = GroupsController::validateGroupEmail('kolab.org', $john);
        $this->assertSame("The specified email is invalid.", $result);

        $result = GroupsController::validateGroupEmail('.@kolab.org', $john);
        $this->assertSame("The specified email is invalid.", $result);

        $result = GroupsController::validateGroupEmail('test123456@localhost', $john);
        $this->assertSame("The specified domain is invalid.", $result);

        $result = GroupsController::validateGroupEmail('test123456@unknown-domain.org', $john);
        $this->assertSame("The specified domain is invalid.", $result);

        // forbidden public domain
        $result = GroupsController::validateGroupEmail('testtest@kolabnow.com', $john);
        $this->assertSame("The specified domain is not available.", $result);

        // existing alias
        $result = GroupsController::validateGroupEmail('jack.daniels@kolab.org', $john);
        $this->assertSame("The specified email is not available.", $result);

        // existing user
        $result = GroupsController::validateGroupEmail('ned@kolab.org', $john);
        $this->assertSame("The specified email is not available.", $result);

        // existing group
        $result = GroupsController::validateGroupEmail('group-test@kolab.org', $john);
        $this->assertSame("The specified email is not available.", $result);

        // valid
        $result = GroupsController::validateGroupEmail('admin@kolab.org', $john);
        $this->assertSame(null, $result);
    }

    /**
     * Group member email address validation.
     */
    public function testValidateMemberEmail(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        // Invalid format
        $result = GroupsController::validateMemberEmail('kolab.org', $john);
        $this->assertSame("The specified email address is invalid.", $result);

        $result = GroupsController::validateMemberEmail('.@kolab.org', $john);
        $this->assertSame("The specified email address is invalid.", $result);

        $result = GroupsController::validateMemberEmail('test123456@localhost', $john);
        $this->assertSame("The specified email address is invalid.", $result);

        // Test local non-existing user
        $result = GroupsController::validateMemberEmail('unknown@kolab.org', $john);
        $this->assertSame("The specified email address does not exist.", $result);

        // Test local existing user
        $result = GroupsController::validateMemberEmail('ned@kolab.org', $john);
        $this->assertSame(null, $result);

        // Test existing user, but not in the same account
        $result = GroupsController::validateMemberEmail('jeroen@jeroen.jeroen', $john);
        $this->assertSame(null, $result);

        // Valid address
        $result = GroupsController::validateMemberEmail('test@google.com', $john);
        $this->assertSame(null, $result);
    }
}
