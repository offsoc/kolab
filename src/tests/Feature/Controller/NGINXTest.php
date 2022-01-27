<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

class NGINXTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $john = $this->getTestUser('john@kolab.org');
        \App\CompanionApp::where('user_id', $john->id)->delete();
        \App\AuthAttempt::where('user_id', $john->id)->delete();
        $john->setSettings(
            [
                // 'limit_geo' => json_encode(["CH"]),
                'guam_enabled' => false,
                '2fa_enabled' => false
            ]
        );
        $this->useServicesUrl();
    }

    public function tearDown(): void
    {

        $john = $this->getTestUser('john@kolab.org');
        \App\CompanionApp::where('user_id', $john->id)->delete();
        \App\AuthAttempt::where('user_id', $john->id)->delete();
        $john->setSettings(
            [
                // 'limit_geo' => json_encode(["CH"]),
                'guam_enabled' => false,
                '2fa_enabled' => false
            ]
        );
        parent::tearDown();
    }

    /**
     * Test the webhook
     */
    public function testNGINXWebhook(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $response = $this->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        $pass = \App\Utils::generatePassphrase();
        $headers = [
            'Auth-Login-Attempt' => '1',
            'Auth-Method' => 'plain',
            'Auth-Pass' => $pass,
            'Auth-Protocol' => 'imap',
            'Auth-Ssl' => 'on',
            'Auth-User' => 'john@kolab.org',
            'Client-Ip' => '127.0.0.1',
            'Host' => '127.0.0.1',
            'Auth-SSL' => 'on',
            'Auth-SSL-Verify' => 'SUCCESS',
            'Auth-SSL-Subject' => '/CN=example.com',
            'Auth-SSL-Issuer' => '/CN=example.com',
            'Auth-SSL-Serial' => 'C07AD56B846B5BFF',
            'Auth-SSL-Fingerprint' => '29d6a80a123d13355ed16b4b04605e29cb55a5ad'
        ];

        // Pass
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-port', '12143');

        // Invalid Password
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Pass'] = "Invalid";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // Empty Password
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Pass'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // Empty User
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-User'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // Invalid User
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-User'] = "foo@kolab.org";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // Empty Ip
        $modifiedHeaders = $headers;
        $modifiedHeaders['Client-Ip'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // SMTP Auth Protocol
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Protocol'] = "smtp";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-server', '127.0.0.1');
        $response->assertHeader('auth-port', '10465');
        $response->assertHeader('auth-pass', $pass);

        // Empty Auth Protocol
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Protocol'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');


        // Guam
        $john->setSettings(
            [
                'guam_enabled' => true,
            ]
        );

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-server', '127.0.0.1');
        $response->assertHeader('auth-port', '9143');

        // 2-FA without device
        $john->setSettings(
            [
                '2fa_enabled' => true,
            ]
        );
        \App\CompanionApp::where('user_id', $john->id)->delete();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // 2-FA with accepted auth attempt
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($john, "127.0.0.1");
        $authAttempt->accept();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
    }

    /**
     * Test the httpauth webhook
     */
    public function testNGINXHttpAuthHook(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $response = $this->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(401);

        $pass = \App\Utils::generatePassphrase();
        $headers = [
            'Php-Auth-Pw' => $pass,
            'Php-Auth-User' => 'john@kolab.org',
            'X-Forwarded-For' => '127.0.0.1',
            'X-Forwarded-Proto' => 'https',
            'X-Original-Uri' => '/iRony/',
            'X-Real-Ip' => '127.0.0.1',
        ];

        // Pass
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);

        // domain.tld\username
        $modifiedHeaders = $headers;
        $modifiedHeaders['Php-Auth-User'] = "kolab.org\\john";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);

        // Invalid Password
        $modifiedHeaders = $headers;
        $modifiedHeaders['Php-Auth-Pw'] = "Invalid";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);

        // Empty Password
        $modifiedHeaders = $headers;
        $modifiedHeaders['Php-Auth-Pw'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(401);

        // Empty User
        $modifiedHeaders = $headers;
        $modifiedHeaders['Php-Auth-User'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);

        // Invalid User
        $modifiedHeaders = $headers;
        $modifiedHeaders['Php-Auth-User'] = "foo@kolab.org";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);

        // Empty Ip
        $modifiedHeaders = $headers;
        $modifiedHeaders['X-Real-Ip'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);


        // 2-FA without device
        $john->setSettings(
            [
                '2fa_enabled' => true,
            ]
        );
        \App\CompanionApp::where('user_id', $john->id)->delete();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);

        // 2-FA with accepted auth attempt
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($john, "127.0.0.1");
        $authAttempt->accept();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);
    }
}
