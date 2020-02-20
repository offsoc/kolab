<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\UsersController;
use App\Domain;
use App\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('UserEntitlement2A@UserEntitlement.com');
        $this->deleteTestDomain('userscontroller.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('UserEntitlement2A@UserEntitlement.com');
        $this->deleteTestDomain('userscontroller.com');

        parent::tearDown();
    }

    /**
     * Test fetching current user info
     */
    public function testInfo(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $response = $this->actingAs($user)->get("api/auth/info");
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertEquals($user->id, $json['id']);
        $this->assertEquals($user->email, $json['email']);
        $this->assertEquals(User::STATUS_NEW, $json['status']);
        $this->assertTrue(is_array($json['statusInfo']));
    }

    public function testIndex(): void
    {
        $userA = $this->getTestUser('UserEntitlement2A@UserEntitlement.com');

        $response = $this->actingAs($userA, 'api')->get("/api/v4/users/{$userA->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $userA->id]);

        $user = factory(User::class)->create();
        $response = $this->actingAs($user)->get("/api/v4/users/{$userA->id}");
        $response->assertStatus(404);
    }

    public function testLogin(): string
    {
        // Request with no data
        $response = $this->post("api/auth/login", []);
        $response->assertStatus(422);
        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        // Request with invalid password
        $post = ['email' => 'john@kolab.org', 'password' => 'wrong'];
        $response = $this->post("api/auth/login", $post);
        $response->assertStatus(401);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('Invalid username or password.', $json['message']);

        // Valid user+password
        $post = ['email' => 'john@kolab.org', 'password' => 'simple123'];
        $response = $this->post("api/auth/login", $post);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertTrue(!empty($json['access_token']));
        $this->assertEquals(\config('jwt.ttl') * 60, $json['expires_in']);
        $this->assertEquals('bearer', $json['token_type']);

        return $json['access_token'];
    }

    /**
     * Test /api/auth/logout
     *
     * @depends testLogin
     */
    public function testLogout($token): void
    {
        // Request with no token, testing that it requires auth
        $response = $this->post("api/auth/logout");
        $response->assertStatus(401);

        // Test the same using JSON mode
        $response = $this->json('POST', "api/auth/logout", []);
        $response->assertStatus(401);

        // Request with valid token
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->post("api/auth/logout");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('Successfully logged out', $json['message']);

        // Check if it really destroyed the token?
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get("api/auth/info");
        $response->assertStatus(401);
    }

    public function testRefresh(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    public function testStatusInfo(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $user->status = User::STATUS_NEW;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('new', $result['status']);
        $this->assertCount(3, $result['process']);
        $this->assertSame('user-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('user-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(false, $result['process'][1]['state']);
        $this->assertSame('user-imap-ready', $result['process'][2]['label']);
        $this->assertSame(false, $result['process'][2]['state']);

        $user->status |= User::STATUS_LDAP_READY | User::STATUS_IMAP_READY;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('new', $result['status']);
        $this->assertCount(3, $result['process']);
        $this->assertSame('user-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('user-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][1]['state']);
        $this->assertSame('user-imap-ready', $result['process'][2]['label']);
        $this->assertSame(true, $result['process'][2]['state']);

        $user->status |= User::STATUS_ACTIVE;
        $user->save();
//        $domain->status |= Domain::STATUS_VERIFIED;
        $domain->type = Domain::TYPE_EXTERNAL;
        $domain->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('active', $result['status']);
        $this->assertCount(6, $result['process']);
        $this->assertSame('user-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('user-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][1]['state']);
        $this->assertSame('user-imap-ready', $result['process'][2]['label']);
        $this->assertSame(true, $result['process'][2]['state']);
        $this->assertSame('domain-new', $result['process'][3]['label']);
        $this->assertSame(true, $result['process'][3]['state']);
        $this->assertSame('domain-ldap-ready', $result['process'][4]['label']);
        $this->assertSame(false, $result['process'][4]['state']);
//        $this->assertSame('domain-verified', $result['process'][5]['label']);
//        $this->assertSame(true, $result['process'][5]['state']);
        $this->assertSame('domain-confirmed', $result['process'][5]['label']);
        $this->assertSame(false, $result['process'][5]['state']);

        $user->status |= User::STATUS_DELETED;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('deleted', $result['status']);
    }
}
