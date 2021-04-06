<?php

namespace Tests\Feature\Controller;

use App\Domain;
use App\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private $expectedExpiry;

    /**
     * Reset all authentication guards to clear any cache users
     */
    protected function resetAuth()
    {
        $guards = array_keys(config('auth.guards'));

        foreach ($guards as $guard) {
            $guard = $this->app['auth']->guard($guard);

            if ($guard instanceof \Illuminate\Auth\SessionGuard) {
                $guard->logout();
            }
        }

        $protectedProperty = new \ReflectionProperty($this->app['auth'], 'guards');
        $protectedProperty->setAccessible(true);
        $protectedProperty->setValue($this->app['auth'], []);
    }

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestDomain('userscontroller.com');

        $this->expectedExpiry = \config('auth.token_expiry_minutes') * 60;
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
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
        $this->assertEquals(User::STATUS_NEW | User::STATUS_ACTIVE, $json['status']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));
        $this->assertTrue(!isset($json['access_token']));

        // Note: Details of the content are tested in testUserResponse()

        // Test token refresh via the info request
        // First we log in to get the refresh token
        $post = ['email' => 'john@kolab.org', 'password' => 'simple123'];
        $user = $this->getTestUser('john@kolab.org');
        $response = $this->post("api/auth/login", $post);
        $json = $response->json();
        $response = $this->actingAs($user)
            ->post("api/auth/info?refresh=1", ['refresh_token' => $json['refresh_token']]);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('john@kolab.org', $json['email']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(!empty($json['expires_in']));
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
        $user = $this->getTestUser('john@kolab.org');
        $post = ['email' => 'john@kolab.org', 'password' => 'simple123'];
        $response = $this->post("api/auth/login", $post);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(
            ($this->expectedExpiry - 5) < $json['expires_in'] &&
            $json['expires_in'] < ($this->expectedExpiry + 5)
        );
        $this->assertEquals('bearer', $json['token_type']);
        $this->assertEquals($user->id, $json['id']);
        $this->assertEquals($user->email, $json['email']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
        $this->assertTrue(is_array($json['aliases']));

        // Valid user+password (upper-case)
        $post = ['email' => 'John@Kolab.org', 'password' => 'simple123'];
        $response = $this->post("api/auth/login", $post);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(
            ($this->expectedExpiry - 5) < $json['expires_in'] &&
            $json['expires_in'] < ($this->expectedExpiry + 5)
        );
        $this->assertEquals('bearer', $json['token_type']);

        // TODO: We have browser tests for 2FA but we should probably also test it here

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

        // Request with invalid token
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . "foobar"])->post("api/auth/logout");
        $response->assertStatus(401);

        // Request with valid token
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->post("api/auth/logout");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('Successfully logged out.', $json['message']);
        $this->resetAuth();

        // Check if it really destroyed the token?
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get("api/auth/info");
        $response->assertStatus(401);
    }

    /**
     * Test /api/auth/refresh
     */
    public function testRefresh(): void
    {
        // Request with no token, testing that it requires auth
        $response = $this->post("api/auth/refresh");
        $response->assertStatus(401);

        // Test the same using JSON mode
        $response = $this->json('POST', "api/auth/refresh", []);
        $response->assertStatus(401);

        // Login the user to get a valid token
        $post = ['email' => 'john@kolab.org', 'password' => 'simple123'];
        $response = $this->post("api/auth/login", $post);
        $response->assertStatus(200);
        $json = $response->json();
        $token = $json['access_token'];

        $user = $this->getTestUser('john@kolab.org');

        // Request with a valid token
        $response = $this->actingAs($user)->post("api/auth/refresh", ['refresh_token' => $json['refresh_token']]);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue($json['access_token'] != $token);
        $this->assertTrue(
            ($this->expectedExpiry - 5) < $json['expires_in'] &&
            $json['expires_in'] < ($this->expectedExpiry + 5)
        );
        $this->assertEquals('bearer', $json['token_type']);
        $new_token = $json['access_token'];

        // TODO: Shall we invalidate the old token?

        // And if the new token is working
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $new_token])->get("api/auth/info");
        $response->assertStatus(200);
    }
}
