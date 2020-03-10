<?php

namespace Tests\Feature\Controller;

use App\Domain;
use App\Http\Controllers\API\UsersController;
use App\User;
use Illuminate\Support\Facades\Queue;
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
        $this->deleteTestUser('UsersControllerTest2@userscontroller.com');
        $this->deleteTestUser('UsersControllerTest3@userscontroller.com');
        $this->deleteTestUser('UserEntitlement2A@UserEntitlement.com');
        $this->deleteTestUser('john2.doe2@kolab.org');
        $this->deleteTestDomain('userscontroller.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestUser('UsersControllerTest2@userscontroller.com');
        $this->deleteTestUser('UsersControllerTest3@userscontroller.com');
        $this->deleteTestUser('UserEntitlement2A@UserEntitlement.com');
        $this->deleteTestUser('john2.doe2@kolab.org');
        $this->deleteTestDomain('userscontroller.com');

        parent::tearDown();
    }

    /**
     * Test fetching current user info (/api/auth/info)
     */
    public function testInfo(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $response = $this->actingAs($user)->get("api/auth/info");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($user->id, $json['id']);
        $this->assertEquals($user->email, $json['email']);
        $this->assertEquals(User::STATUS_NEW, $json['status']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));

        // Note: Details of the content are tested in testUserResponse()
    }

    /**
     * Test user deleting (DELETE /api/v4/users/<id>)
     */
    public function testDestroy(): void
    {
        // First create some users/accounts to delete
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();

        $john = $this->getTestUser('john@kolab.org');
        $user1 = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $user2 = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $user3 = $this->getTestUser('UsersControllerTest3@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);
        $user1->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user1);
        $user1->assignPackage($package_kolab, $user2);
        $user1->assignPackage($package_kolab, $user3);

        // Test unauth access
        $response = $this->delete("api/v4/users/{$user2->id}");
        $response->assertStatus(401);

        // Test access to other user/account
        $response = $this->actingAs($john)->delete("api/v4/users/{$user2->id}");
        $response->assertStatus(403);
        $response = $this->actingAs($john)->delete("api/v4/users/{$user1->id}");
        $response->assertStatus(403);
        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test that non-controller cannot remove himself
        $response = $this->actingAs($user3)->delete("api/v4/users/{$user3->id}");
        $response->assertStatus(403);

        // Test removing a non-controller user
        $response = $this->actingAs($user1)->delete("api/v4/users/{$user3->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('User deleted successfully.', $json['message']);

        // Test removing self (an account with users)
        $response = $this->actingAs($user1)->delete("api/v4/users/{$user1->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('User deleted successfully.', $json['message']);
    }

    /**
     * Test user deleting (DELETE /api/v4/users/<id>)
     */
    public function testDestroyByController(): void
    {
        // Create an account with additional controller - $user2
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $user1 = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $user2 = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $user3 = $this->getTestUser('UsersControllerTest3@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);
        $user1->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user1);
        $user1->assignPackage($package_kolab, $user2);
        $user1->assignPackage($package_kolab, $user3);
        $user1->wallets()->first()->addController($user2);

        // TODO/FIXME:
        //   For now controller can delete himself, as well as
        //   the whole account he has control to, including the owner
        //   Probably he should not be able to do either of those
        //   However, this is not 0-regression scenario as we
        //   do not fully support additional controllers.

        //$response = $this->actingAs($user2)->delete("api/v4/users/{$user2->id}");
        //$response->assertStatus(403);

        $response = $this->actingAs($user2)->delete("api/v4/users/{$user3->id}");
        $response->assertStatus(200);

        $response = $this->actingAs($user2)->delete("api/v4/users/{$user1->id}");
        $response->assertStatus(200);

        // Note: More detailed assertions in testDestroy() above

        $this->assertTrue($user1->fresh()->trashed());
        $this->assertTrue($user2->fresh()->trashed());
        $this->assertTrue($user3->fresh()->trashed());
    }

    /**
     * Test user listing (GET /api/v4/users)
     */
    public function testIndex(): void
    {
        // Test unauth access
        $response = $this->get("api/v4/users");
        $response->assertStatus(401);

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $response = $this->actingAs($jack)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(0, $json);

        $response = $this->actingAs($john)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame($jack->email, $json[0]['email']);
        $this->assertSame($john->email, $json[1]['email']);
        $this->assertSame($ned->email, $json[2]['email']);

        $response = $this->actingAs($ned)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame($jack->email, $json[0]['email']);
        $this->assertSame($john->email, $json[1]['email']);
        $this->assertSame($ned->email, $json[2]['email']);
    }

    /**
     * Test /api/auth/login
     */
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
        $this->assertEquals('Successfully logged out.', $json['message']);

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
        $domain->status |= Domain::STATUS_VERIFIED;
        $domain->type = Domain::TYPE_EXTERNAL;
        $domain->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('active', $result['status']);
        $this->assertCount(7, $result['process']);
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
        $this->assertSame('domain-verified', $result['process'][5]['label']);
        $this->assertSame(true, $result['process'][5]['state']);
        $this->assertSame('domain-confirmed', $result['process'][6]['label']);
        $this->assertSame(false, $result['process'][6]['state']);

        $user->status |= User::STATUS_DELETED;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('deleted', $result['status']);
    }

    /**
     * Test user data response used in show and info actions
     */
    public function testUserResponse(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $result = $this->invokeMethod(new UsersController(), 'userResponse', [$user]);

        $this->assertEquals($user->id, $result['id']);
        $this->assertEquals($user->email, $result['email']);
        $this->assertEquals($user->status, $result['status']);
        $this->assertTrue(is_array($result['statusInfo']));

        $this->assertTrue(is_array($result['aliases']));
        $this->assertCount(1, $result['aliases']);
        $this->assertSame('john.doe@kolab.org', $result['aliases'][0]);

        $this->assertTrue(is_array($result['settings']));
        $this->assertSame('US', $result['settings']['country']);
        $this->assertSame('USD', $result['settings']['currency']);

        $this->assertTrue(is_array($result['accounts']));
        $this->assertTrue(is_array($result['wallets']));
        $this->assertCount(0, $result['accounts']);
        $this->assertCount(1, $result['wallets']);
        $this->assertSame($wallet->id, $result['wallet']['id']);

        $ned = $this->getTestUser('ned@kolab.org');
        $ned_wallet = $ned->wallets()->first();
        $result = $this->invokeMethod(new UsersController(), 'userResponse', [$ned]);

        $this->assertEquals($ned->id, $result['id']);
        $this->assertEquals($ned->email, $result['email']);
        $this->assertTrue(is_array($result['accounts']));
        $this->assertTrue(is_array($result['wallets']));
        $this->assertCount(1, $result['accounts']);
        $this->assertCount(1, $result['wallets']);
        $this->assertSame($wallet->id, $result['wallet']['id']);
        $this->assertSame($wallet->id, $result['accounts'][0]['id']);
        $this->assertSame($ned_wallet->id, $result['wallets'][0]['id']);
    }

    /**
     * Test fetching user data/profile (GET /api/v4/users/<user-id>)
     */
    public function testShow(): void
    {
        $userA = $this->getTestUser('UserEntitlement2A@UserEntitlement.com');

        // Test getting profile of self
        $response = $this->actingAs($userA, 'api')->get("/api/v4/users/{$userA->id}");

        $json = $response->json();

        $response->assertStatus(200);
        $this->assertEquals($userA->id, $json['id']);
        $this->assertEquals($userA->email, $json['email']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        // Test unauthorized access to a profile of other user
        $response = $this->actingAs($jack)->get("/api/v4/users/{$userA->id}");
        $response->assertStatus(403);

        // Test authorized access to a profile of other user
        // Ned: Additional account controller
        $response = $this->actingAs($ned)->get("/api/v4/users/{$john->id}");
        $response->assertStatus(200);
        $response = $this->actingAs($ned)->get("/api/v4/users/{$jack->id}");
        $response->assertStatus(200);

        // John: Account owner
        $response = $this->actingAs($john)->get("/api/v4/users/{$jack->id}");
        $response->assertStatus(200);
        $response = $this->actingAs($john)->get("/api/v4/users/{$ned->id}");
        $response->assertStatus(200);
    }

    /**
     * Test user creation (POST /api/v4/users)
     */
    public function testStore(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');

        // Test empty request
        $response = $this->actingAs($john)->post("/api/v4/users", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The email field is required.", $json['errors']['email']);
        $this->assertSame("The password field is required.", $json['errors']['password'][0]);
        $this->assertCount(2, $json);

        // Test access by user not being a wallet controller
        $post = ['first_name' => 'Test'];
        $response = $this->actingAs($jack)->post("/api/v4/users", $post);
        $json = $response->json();

        $response->assertStatus(403);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['password' => '12345678', 'email' => 'invalid'];
        $response = $this->actingAs($john)->post("/api/v4/users", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The password confirmation does not match.', $json['errors']['password'][0]);
        $this->assertSame('The specified email is invalid.', $json['errors']['email']);

        // Test existing user email
        $post = [
            'password' => 'simple',
            'password_confirmation' => 'simple',
            'first_name' => 'John2',
            'last_name' => 'Doe2',
            'email' => 'jack.daniels@kolab.org',
        ];

        $response = $this->actingAs($john)->post("/api/v4/users", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The specified email is not available.', $json['errors']['email']);

        // Test full user data
        $post = [
            'password' => 'simple',
            'password_confirmation' => 'simple',
            'first_name' => 'John2',
            'last_name' => 'Doe2',
            'email' => 'john2.doe2@kolab.org',
            'aliases' => ['useralias1@kolab.org', 'useralias2@kolab.org']
        ];

        $response = $this->actingAs($john)->post("/api/v4/users", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("User created successfully.", $json['message']);
        $this->assertCount(2, $json);

        $user = User::where('email', 'john2.doe2@kolab.org')->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John2', $user->getSetting('first_name'));
        $this->assertSame('Doe2', $user->getSetting('last_name'));
        $aliases = $user->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('useralias1@kolab.org', $aliases[0]->alias);
        $this->assertSame('useralias2@kolab.org', $aliases[1]->alias);

        // TODO: Test assigning a package to new user
        // TODO: Test the wallet to which the new user should be assigned to

        // Test acting as account controller (not owner)
        /*
        // FIXME: How do we know to which wallet the new user should be assigned to?

        $this->deleteTestUser('john2.doe2@kolab.org');
        $response = $this->actingAs($ned)->post("/api/v4/users", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        */

        $this->markTestIncomplete();
    }

    /**
     * Test user update (PUT /api/v4/users/<user-id>)
     */
    public function testUpdate(): void
    {
        $userA = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $domain = $this->getTestDomain(
            'userscontroller.com',
            ['status' => Domain::STATUS_NEW, 'type' => Domain::TYPE_EXTERNAL]
        );

        // Test unauthorized update of other user profile
        $response = $this->actingAs($jack)->get("/api/v4/users/{$userA->id}", []);
        $response->assertStatus(403);

        // Test authorized update of account owner by account controller
        $response = $this->actingAs($ned)->get("/api/v4/users/{$john->id}", []);
        $response->assertStatus(200);

        // Test updating of self (empty request)
        $response = $this->actingAs($userA)->put("/api/v4/users/{$userA->id}", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("User data updated successfully.", $json['message']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['password' => '12345678', 'currency' => 'invalid'];
        $response = $this->actingAs($userA)->put("/api/v4/users/{$userA->id}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The password confirmation does not match.', $json['errors']['password'][0]);
        $this->assertSame('The currency must be 3 characters.', $json['errors']['currency'][0]);

        // Test full profile update including password
        $post = [
            'password' => 'simple',
            'password_confirmation' => 'simple',
            'first_name' => 'John2',
            'last_name' => 'Doe2',
            'phone' => '+123 123 123',
            'external_email' => 'external@gmail.com',
            'billing_address' => 'billing',
            'country' => 'CH',
            'currency' => 'CHF',
            'aliases' => ['useralias1@' . \config('app.domain'), 'useralias2@' . \config('app.domain')]
        ];

        $response = $this->actingAs($userA)->put("/api/v4/users/{$userA->id}", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("User data updated successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertTrue($userA->password != $userA->fresh()->password);
        unset($post['password'], $post['password_confirmation'], $post['aliases']);
        foreach ($post as $key => $value) {
            $this->assertSame($value, $userA->getSetting($key));
        }
        $aliases = $userA->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('useralias1@' . \config('app.domain'), $aliases[0]->alias);
        $this->assertSame('useralias2@' . \config('app.domain'), $aliases[1]->alias);

        // Test unsetting values
        $post = [
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'external_email' => '',
            'billing_address' => '',
            'country' => '',
            'currency' => '',
            'aliases' => ['useralias2@' . \config('app.domain')]
        ];

        $response = $this->actingAs($userA)->put("/api/v4/users/{$userA->id}", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("User data updated successfully.", $json['message']);
        $this->assertCount(2, $json);
        unset($post['aliases']);
        foreach ($post as $key => $value) {
            $this->assertNull($userA->getSetting($key));
        }
        $aliases = $userA->aliases()->get();
        $this->assertCount(1, $aliases);
        $this->assertSame('useralias2@' . \config('app.domain'), $aliases[0]->alias);

        // Test error on setting an alias to other user's domain
        // and missing password confirmation
        $post = [
            'password' => 'simple123',
            'aliases' => ['useralias2@' . \config('app.domain'), 'useralias1@kolab.org']
        ];

        $response = $this->actingAs($userA)->put("/api/v4/users/{$userA->id}", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertCount(1, $json['errors']['aliases']);
        $this->assertSame("The specified domain is not available.", $json['errors']['aliases'][1]);
        $this->assertSame("The password confirmation does not match.", $json['errors']['password'][0]);

        // Test authorized update of other user
        $response = $this->actingAs($ned)->get("/api/v4/users/{$jack->id}", []);
        $response->assertStatus(200);

        // TODO: Test error on aliases with invalid/non-existing/other-user's domain
    }

    /**
     * List of alias validation cases for testValidateEmail()
     *
     * @return array Arguments for testValidateEmail()
     */
    public function dataValidateEmail(): array
    {
        $this->refreshApplication();
        $public_domains = Domain::getPublicDomains();
        $domain = reset($public_domains);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');

        return [
            // Invalid format
            ["$domain", $john, true, 'The specified alias is invalid.'],
            [".@$domain", $john, true, 'The specified alias is invalid.'],
            ["test123456@localhost", $john, true, 'The specified domain is invalid.'],
            ["test123456@unknown-domain.org", $john, true, 'The specified domain is invalid.'],

            ["$domain", $john, false, 'The specified email is invalid.'],
            [".@$domain", $john, false, 'The specified email is invalid.'],

            // forbidden local part on public domains
            ["admin@$domain", $john, true, 'The specified alias is not available.'],
            ["administrator@$domain", $john, true, 'The specified alias is not available.'],

            // forbidden (other user's domain)
            ["testtest@kolab.org", $user, true, 'The specified domain is not available.'],

            // existing alias of other user
            ["jack.daniels@kolab.org", $john, true, 'The specified alias is not available.'],

            // existing user
            ["jack@kolab.org", $john, true, 'The specified alias is not available.'],

            // valid (user domain)
            ["admin@kolab.org", $john, true, null],

            // valid (public domain)
            ["test.test@$domain", $john, true, null],
        ];
    }

    /**
     * User email/alias validation.
     *
     * Note: Technically these include unit tests, but let's keep it here for now.
     * FIXME: Shall we do a http request for each case?
     *
     * @dataProvider dataValidateEmail
     */
    public function testValidateEmail($alias, $user, $is_alias, $expected_result): void
    {
        $result = $this->invokeMethod(new UsersController(), 'validateEmail', [$alias, $user, $is_alias]);

        $this->assertSame($expected_result, $result);
    }
}
