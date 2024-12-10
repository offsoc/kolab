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
        $this->app['auth']->forgetGuards();
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

        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();

        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('limit_geo', null);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestDomain('userscontroller.com');

        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();

        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('limit_geo', null);

        parent::tearDown();
    }

    /**
     * Test fetching current user info (/api/auth/info)
     */
    public function testInfo(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com', ['status' => User::STATUS_NEW]);
        $domain = $this->getTestDomain('userscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $response = $this->get("api/auth/info");
        $response->assertStatus(401);

        $response = $this->actingAs($user)->get("api/auth/info");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($user->id, $json['id']);
        $this->assertEquals($user->email, $json['email']);
        $this->assertEquals(User::STATUS_NEW, $json['status']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));
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
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(!empty($json['expires_in']));
    }

    /**
     * Test fetching current user location (/api/auth/location)
     */
    public function testLocation(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');

        // Authentication required
        $response = $this->get("api/auth/location");
        $response->assertStatus(401);

        $headers = ['X-Client-IP' => '127.0.0.2'];

        $response = $this->actingAs($user)->withHeaders($headers)->get("api/auth/location");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('127.0.0.2', $json['ipAddress']);
        $this->assertSame('', $json['countryCode']);

        \App\IP4Net::create([
                'net_number' => '127.0.0.0',
                'net_broadcast' => '127.255.255.255',
                'net_mask' => 8,
                'country' => 'US',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

        $response = $this->actingAs($user)->withHeaders($headers)->get("api/auth/location");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('127.0.0.2', $json['ipAddress']);
        $this->assertSame('US', $json['countryCode']);
    }

    /**
     * Test /api/auth/login
     */
    public function testLogin(): string
    {
        $user = $this->getTestUser('john@kolab.org');

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
        $this->assertTrue(
            ($this->expectedExpiry - 5) < $json['expires_in'] &&
            $json['expires_in'] < ($this->expectedExpiry + 5)
        );
        $this->assertEquals('bearer', $json['token_type']);
        $this->assertEquals($user->id, $json['id']);
        $this->assertEquals($user->email, $json['email']);
        $this->assertTrue(is_array($json['statusInfo']));
        $this->assertTrue(is_array($json['settings']));

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
     * Test service account login attempt
     */
    public function testLoginServiceAccount(): void
    {
        $user = $this->getTestUser('cyrus-admin');
        $user->role = \App\User::ROLE_SERVICE;
        $user->password = 'simple123';
        $user->save();

        // Request with service account
        $post = ['email' => 'cyrus-admin', 'password' => 'simple123'];
        $response = $this->post("api/auth/login", $post);
        $response->assertStatus(401);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('Invalid username or password.', $json['message']);
    }

    /**
     * Test /api/auth/login with geo-lockin
     */
    public function testLoginGeoLock(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('limit_geo', json_encode(['US']));

        $headers['X-Client-IP'] = '127.0.0.2';
        $post = ['email' => 'john@kolab.org', 'password' => 'simple123'];

        $response = $this->withHeaders($headers)->post("api/auth/login", $post);
        $response->assertStatus(401);

        $json = $response->json();

        $this->assertSame("Invalid username or password.", $json['message']);
        $this->assertSame('error', $json['status']);

        \App\IP4Net::create([
                'net_number' => '127.0.0.0',
                'net_broadcast' => '127.255.255.255',
                'net_mask' => 8,
                'country' => 'US',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

        $response = $this->withHeaders($headers)->post("api/auth/login", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue(!empty($json['access_token']));
        $this->assertEquals($user->id, $json['id']);
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

    /**
     * Test OAuth2 Authorization Code Flow
     */
    public function testOAuthAuthorizationCodeFlow(): void
    {
        $user = $this->getTestUser('john@kolab.org');

        // Request unauthenticated, testing that it requires auth
        $response = $this->post("api/oauth/approve");
        $response->assertStatus(401);

        // Request authenticated, invalid POST data
        $post = ['response_type' => 'unknown'];
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('unsupported_response_type', $json['error']);
        $this->assertSame('Invalid authorization request.', $json['message']);

        // Request authenticated, invalid POST data
        $post = [
            'client_id' => 'unknown',
            'response_type' => 'code',
            'scope' => 'email', // space-separated
            'state' => 'state', // optional
            'nonce' => 'nonce', // optional
        ];
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('invalid_client', $json['error']);
        $this->assertSame('Client authentication failed', $json['message']);

        $client = \App\Auth\PassportClient::find(\config('auth.synapse.client_id'));

        $post['client_id'] = $client->id;

        // Request authenticated, invalid scope
        $post['scope'] = 'unknown';
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame('invalid_scope', $json['error']);
        $this->assertSame('The requested scope is invalid, unknown, or malformed', $json['message']);

        // Request authenticated, valid POST data
        $post['scope'] = 'email';
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $url = $json['redirectUrl'];
        parse_str(parse_url($url, \PHP_URL_QUERY), $params);

        $this->assertTrue(str_starts_with($url, $client->redirect . '?'));
        $this->assertCount(2, $params);
        $this->assertSame('state', $params['state']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{50,}$/', $params['code']);
        $this->assertSame('success', $json['status']);

        // Note: We do not validate the code trusting Passport to do the right thing. Should we not?

        // Token endpoint tests

        // Valid authorization code, but invalid secret
        $post = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => 'invalid',
            // 'redirect_uri' => '',
            'code' => $params['code'],
        ];

        // Note: This is a 'web' route, not 'api'
        $this->resetAuth(); // reset guards
        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(401);

        $json = $response->json();

        $this->assertSame('invalid_client', $json['error']);
        $this->assertTrue(!empty($json['error_description']));

        // Valid authorization code
        $post['client_secret'] = \config('auth.synapse.client_secret');
        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(200);

        $params = $response->json();

        $this->assertSame('Bearer', $params['token_type']);
        $this->assertTrue(!empty($params['access_token']));
        $this->assertTrue(!empty($params['refresh_token']));
        $this->assertTrue(!empty($params['expires_in']));
        $this->assertTrue(empty($params['id_token']));

        // Invalid authorization code
        // Note: The code is being revoked on use, so we expect it does not work anymore
        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(400);

        $json = $response->json();

        $this->assertSame('invalid_request', $json['error']);
        $this->assertTrue(!empty($json['error_description']));

        // Token refresh
        unset($post['code']);
        $post['grant_type'] = 'refresh_token';
        $post['refresh_token'] = $params['refresh_token'];

        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('Bearer', $json['token_type']);
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(!empty($json['refresh_token']));
        $this->assertTrue(!empty($json['expires_in']));
        $this->assertTrue(empty($json['id_token']));
        $this->assertNotEquals($json['access_token'], $params['access_token']);
        $this->assertNotEquals($json['refresh_token'], $params['refresh_token']);

        $token = $json['access_token'];

        // Validate the access token works on /oauth/userinfo endpoint
        $this->resetAuth(); // reset guards
        $headers = ['Authorization' => 'Bearer ' . $token];
        $response = $this->withHeaders($headers)->get("/oauth/userinfo");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($user->id, $json['sub']);
        $this->assertEquals($user->email, $json['email']);

        // Validate that the access token does not give access to API other than /oauth/userinfo
        $this->resetAuth(); // reset guards
        $response = $this->withHeaders($headers)->get("/api/auth/location");
        $response->assertStatus(403);
    }

    /**
     * Test Oauth approve end-point in ifSeen mode
     */
    public function testOAuthApprovePrompt(): void
    {
        // HTTP_HOST is not set in tests for some reason, but it's required down the line
        $host = parse_url(\App\Utils::serviceUrl('/'), \PHP_URL_HOST);
        $_SERVER['HTTP_HOST'] = $host;

        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $client = \App\Auth\PassportClient::find(\config('auth.sso.client_id'));

        $post = [
            'client_id' => $client->id,
            'response_type' => 'code',
            'scope' => 'openid email auth.token',
            'state' => 'state',
            'nonce' => 'nonce',
            'ifSeen' => '1',
        ];

        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $claims = [
            'email' => 'See your email address',
            'auth.token' => 'Have read and write access to all your data',
        ];

        $this->assertSame('prompt', $json['status']);
        $this->assertSame($client->name, $json['client']['name']);
        $this->assertSame($client->redirect, $json['client']['url']);
        $this->assertSame($claims, $json['client']['claims']);

        // Approve the request
        $post['ifSeen'] = 0;
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertTrue(!empty($json['redirectUrl']));

        // Second request with ifSeen=1 should succeed with the code
        $post['ifSeen'] = 1;
        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertTrue(!empty($json['redirectUrl']));
    }

    /**
     * Test OpenID-Connect Authorization Code Flow
     */
    public function testOIDCAuthorizationCodeFlow(): void
    {
        // HTTP_HOST is not set in tests for some reason, but it's required down the line
        $host = parse_url(\App\Utils::serviceUrl('/'), \PHP_URL_HOST);
        $_SERVER['HTTP_HOST'] = $host;

        $user = $this->getTestUser('john@kolab.org');
        $client = \App\Auth\PassportClient::find(\config('auth.sso.client_id'));

        // Note: Invalid input cases were tested above, we omit them here

        // This is essentially the same as for OAuth2, but with extended scopes
        $post = [
            'client_id' => $client->id,
            'response_type' => 'code',
            'scope' => 'openid email auth.token',
            'state' => 'state',
            'nonce' => 'nonce',
        ];

        $response = $this->actingAs($user)->post("api/oauth/approve", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $url = $json['redirectUrl'];
        parse_str(parse_url($url, \PHP_URL_QUERY), $params);

        $this->assertTrue(str_starts_with($url, $client->redirect . '?'));
        $this->assertCount(2, $params);
        $this->assertSame('state', $params['state']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{50,}$/', $params['code']);
        $this->assertSame('success', $json['status']);

        // Token endpoint tests
        $post = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => \config('auth.synapse.client_secret'),
            'code' => $params['code'],
        ];

        $this->resetAuth(); // reset guards state
        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(200);

        $params = $response->json();

        $this->assertSame('Bearer', $params['token_type']);
        $this->assertTrue(!empty($params['access_token']));
        $this->assertTrue(!empty($params['refresh_token']));
        $this->assertTrue(!empty($params['id_token']));
        $this->assertTrue(!empty($params['expires_in']));

        $token = $this->parseIdToken($params['id_token']);

        $this->assertSame('JWT', $token['typ']);
        $this->assertSame('RS256', $token['alg']);
        $this->assertSame('nonce', $token['nonce']);
        $this->assertSame(url('/'), $token['iss']);
        $this->assertSame($user->email, $token['email']);
        $this->assertSame((string) $user->id, \App\Auth\Utils::tokenValidate($token['auth.token']));

        // TODO: Validate JWT token properly

        // Token refresh
        unset($post['code']);
        $post['grant_type'] = 'refresh_token';
        $post['refresh_token'] = $params['refresh_token'];

        $response = $this->post("/oauth/token", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('Bearer', $json['token_type']);
        $this->assertTrue(!empty($json['access_token']));
        $this->assertTrue(!empty($json['refresh_token']));
        $this->assertTrue(!empty($json['id_token']));
        $this->assertTrue(!empty($json['expires_in']));

        // Validate the access token works on /oauth/userinfo endpoint
        $this->resetAuth(); // reset guards state
        $headers = ['Authorization' => 'Bearer ' . $json['access_token']];
        $response = $this->withHeaders($headers)->get("/oauth/userinfo");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($user->id, $json['sub']);
        $this->assertEquals($user->email, $json['email']);

        // Validate that the access token does not give access to API other than /oauth/userinfo
        $this->resetAuth(); // reset guards state
        $response = $this->withHeaders($headers)->get("/api/auth/location");
        $response->assertStatus(403);
    }

    /**
     * Test to make sure Passport routes are disabled
     */
    public function testPassportDisabledRoutes(): void
    {
        $this->post("/oauth/authorize", [])->assertStatus(405);
        $this->post("/oauth/token/refresh", [])->assertStatus(405);
    }

    /**
     * Parse JWT token into an array
     */
    private function parseIdToken($token): array
    {
        [$headb64, $bodyb64, $cryptob64] = explode('.', $token);

        $header = json_decode(base64_decode(strtr($headb64, '-_', '+/'), true), true);
        $body = json_decode(base64_decode(strtr($bodyb64, '-_', '+/'), true), true);

        return array_merge($header, $body);
    }
}
