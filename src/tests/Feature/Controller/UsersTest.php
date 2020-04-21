<?php

namespace Tests\Feature\Controller;

use App\Discount;
use App\Domain;
use App\Http\Controllers\API\V4\UsersController;
use App\Package;
use App\Sku;
use App\User;
use App\Wallet;
use Carbon\Carbon;
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

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();
        $user->status |= User::STATUS_IMAP_READY;
        $user->save();
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

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();
        $user->status |= User::STATUS_IMAP_READY;
        $user->save();

        parent::tearDown();
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
        //   Probably he should not be able to do none of those
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
        $joe = $this->getTestUser('joe@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $response = $this->actingAs($jack)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(0, $json);

        $response = $this->actingAs($john)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame($jack->email, $json[0]['email']);
        $this->assertSame($joe->email, $json[1]['email']);
        $this->assertSame($john->email, $json[2]['email']);
        $this->assertSame($ned->email, $json[3]['email']);
        // Values below are tested by Unit tests
        $this->assertArrayHasKey('isDeleted', $json[0]);
        $this->assertArrayHasKey('isSuspended', $json[0]);
        $this->assertArrayHasKey('isActive', $json[0]);
        $this->assertArrayHasKey('isLdapReady', $json[0]);
        $this->assertArrayHasKey('isImapReady', $json[0]);

        $response = $this->actingAs($ned)->get("/api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame($jack->email, $json[0]['email']);
        $this->assertSame($joe->email, $json[1]['email']);
        $this->assertSame($john->email, $json[2]['email']);
        $this->assertSame($ned->email, $json[3]['email']);
    }

    /**
     * Test fetching user data/profile (GET /api/v4/users/<user-id>)
     */
    public function testShow(): void
    {
        $userA = $this->getTestUser('UserEntitlement2A@UserEntitlement.com');

        // Test getting profile of self
        $response = $this->actingAs($userA)->get("/api/v4/users/{$userA->id}");

        $json = $response->json();

        $response->assertStatus(200);
        $this->assertEquals($userA->id, $json['id']);
        $this->assertEquals($userA->email, $json['email']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));
        $this->assertSame([], $json['skus']);
        // Values below are tested by Unit tests
        $this->assertArrayHasKey('isDeleted', $json);
        $this->assertArrayHasKey('isSuspended', $json);
        $this->assertArrayHasKey('isActive', $json);
        $this->assertArrayHasKey('isLdapReady', $json);
        $this->assertArrayHasKey('isImapReady', $json);

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

        $json = $response->json();

        $storage_sku = Sku::where('title', 'storage')->first();
        $groupware_sku = Sku::where('title', 'groupware')->first();
        $mailbox_sku = Sku::where('title', 'mailbox')->first();
        $secondfactor_sku = Sku::where('title', '2fa')->first();

        $this->assertCount(5, $json['skus']);

        $this->assertSame(2, $json['skus'][$storage_sku->id]['count']);
        $this->assertSame(1, $json['skus'][$groupware_sku->id]['count']);
        $this->assertSame(1, $json['skus'][$mailbox_sku->id]['count']);
        $this->assertSame(1, $json['skus'][$secondfactor_sku->id]['count']);
    }

    /**
     * Test fetching user status (GET /api/v4/users/<user-id>/status)
     * and forcing setup process update (?refresh=1)
     *
     * @group imap
     * @group dns
     */
    public function testStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        // Test unauthorized access
        $response = $this->actingAs($jack)->get("/api/v4/users/{$john->id}/status");
        $response->assertStatus(403);

        $john->status ^= User::STATUS_IMAP_READY;
        $john->save();

        // Get user status
        $response = $this->actingAs($john)->get("/api/v4/users/{$john->id}/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse($json['isImapReady']);
        $this->assertFalse($json['isReady']);
        $this->assertCount(7, $json['process']);
        $this->assertSame('user-imap-ready', $json['process'][2]['label']);
        $this->assertSame(false, $json['process'][2]['state']);
        $this->assertTrue(empty($json['status']));
        $this->assertTrue(empty($json['message']));

        // Now "reboot" the process and verify the user in imap syncronously
        $response = $this->actingAs($john)->get("/api/v4/users/{$john->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isImapReady']);
        $this->assertFalse($json['isReady']);
        $this->assertCount(7, $json['process']);
        $this->assertSame('user-imap-ready', $json['process'][2]['label']);
        $this->assertSame(true, $json['process'][2]['state']);
        $this->assertSame('domain-confirmed', $json['process'][6]['label']);
        $this->assertSame(false, $json['process'][6]['state']);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to verify an ownership of a domain.', $json['message']);

        // TODO: Test completing all process steps
    }

    /**
     * Test UsersController::statusInfo()
     */
    public function testStatusInfo(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $user->created_at = Carbon::now();
        $user->status = User::STATUS_NEW;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertFalse($result['isReady']);
        $this->assertCount(3, $result['process']);
        $this->assertSame('user-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('user-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(false, $result['process'][1]['state']);
        $this->assertSame('user-imap-ready', $result['process'][2]['label']);
        $this->assertSame(false, $result['process'][2]['state']);
        $this->assertSame('running', $result['processState']);

        $user->created_at = Carbon::now()->subSeconds(181);
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertSame('failed', $result['processState']);

        $user->status |= User::STATUS_LDAP_READY | User::STATUS_IMAP_READY;
        $user->save();

        $result = UsersController::statusInfo($user);

        $this->assertTrue($result['isReady']);
        $this->assertCount(3, $result['process']);
        $this->assertSame('user-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('user-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][1]['state']);
        $this->assertSame('user-imap-ready', $result['process'][2]['label']);
        $this->assertSame(true, $result['process'][2]['state']);
        $this->assertSame('done', $result['processState']);

        $domain->status |= Domain::STATUS_VERIFIED;
        $domain->type = Domain::TYPE_EXTERNAL;
        $domain->save();

        $result = UsersController::statusInfo($user);

        $this->assertFalse($result['isReady']);
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

        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();

        $post = [
            'password' => 'simple',
            'password_confirmation' => 'simple',
            'first_name' => 'John2',
            'last_name' => 'Doe2',
            'email' => 'john2.doe2@kolab.org',
            'organization' => 'TestOrg',
            'aliases' => ['useralias1@kolab.org', 'useralias2@kolab.org'],
        ];

        // Missing package
        $response = $this->actingAs($john)->post("/api/v4/users", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Package is required.", $json['errors']['package']);
        $this->assertCount(2, $json);

        // Invalid package
        $post['package'] = $package_domain->id;
        $response = $this->actingAs($john)->post("/api/v4/users", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Invalid package selected.", $json['errors']['package']);
        $this->assertCount(2, $json);

        // Test full and valid data
        $post['package'] = $package_kolab->id;
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
        $this->assertSame('TestOrg', $user->getSetting('organization'));
        $aliases = $user->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('useralias1@kolab.org', $aliases[0]->alias);
        $this->assertSame('useralias2@kolab.org', $aliases[1]->alias);
        // Assert the new user entitlements
        $this->assertUserEntitlements($user, ['groupware', 'mailbox', 'storage', 'storage']);
        // Assert the wallet to which the new user should be assigned to
        $wallet = $user->wallet();
        $this->assertSame($john->wallets()->first()->id, $wallet->id);

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
            'organization' => 'TestOrg',
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
            'organization' => '',
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

        // Create entitlements and additional user for following tests
        $owner = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $user = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $package_domain = Package::where('title', 'domain-hosting')->first();
        $package_kolab = Package::where('title', 'kolab')->first();
        $package_lite = Package::where('title', 'lite')->first();
        $sku_mailbox = Sku::where('title', 'mailbox')->first();
        $sku_storage = Sku::where('title', 'storage')->first();
        $sku_groupware = Sku::where('title', 'groupware')->first();

        $domain = $this->getTestDomain(
            'userscontroller.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $domain->assignPackage($package_domain, $owner);
        $owner->assignPackage($package_kolab);
        $owner->assignPackage($package_lite, $user);

        // Non-controller cannot update his own entitlements
        $post = ['skus' => []];
        $response = $this->actingAs($user)->put("/api/v4/users/{$user->id}", $post);
        $response->assertStatus(422);

        // Test updating entitlements
        $post = [
            'skus' => [
                $sku_mailbox->id => 1,
                $sku_storage->id => 3,
                $sku_groupware->id => 1,
            ],
        ];

        $response = $this->actingAs($owner)->put("/api/v4/users/{$user->id}", $post);
        $response->assertStatus(200);

        $storage_cost = $user->entitlements()
            ->where('sku_id', $sku_storage->id)
            ->orderBy('cost')
            ->pluck('cost')->all();

        $this->assertUserEntitlements($user, ['groupware', 'mailbox', 'storage', 'storage', 'storage']);
        $this->assertSame([0, 0, 25], $storage_cost);
    }

    /**
     * Test UsersController::updateEntitlements()
     */
    public function testUpdateEntitlements(): void
    {
        // TODO: Test more cases of entitlements update
        $this->markTestIncomplete();
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
        $this->assertArrayNotHasKey('discount', $result['wallet']);

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

        // Test discount in a response
        $discount = Discount::where('code', 'TEST')->first();
        $wallet->discount()->associate($discount);
        $wallet->save();
        $user->refresh();

        $result = $this->invokeMethod(new UsersController(), 'userResponse', [$user]);

        $this->assertEquals($user->id, $result['id']);
        $this->assertSame($discount->id, $result['wallet']['discount_id']);
        $this->assertSame($discount->discount, $result['wallet']['discount']);
        $this->assertSame($discount->description, $result['wallet']['discount_description']);
        $this->assertSame($discount->id, $result['wallets'][0]['discount_id']);
        $this->assertSame($discount->discount, $result['wallets'][0]['discount']);
        $this->assertSame($discount->description, $result['wallets'][0]['discount_description']);
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
        $result = $this->invokeMethod(new \App\Utils(), 'validateEmail', [$alias, $user, $is_alias]);

        $this->assertSame($expected_result, $result);
    }
}
