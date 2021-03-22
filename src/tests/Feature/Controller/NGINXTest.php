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

        $response = $this->actingAs($john)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'NO');

        $headers = [
            'Auth-Login-Attempt' => '1',
            'Auth-Method' => 'plain',
            'Auth-Pass' => 'simple123',
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
        $response = $this->actingAs($john)->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-port', '11993');

        // Invalid Password
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Pass'] = "Invalid";
        $response = $this->actingAs($john)->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'NO');


        // Guam
        $john->setSettings(
            [
                'guam_enabled' => true,
            ]
        );

        $response = $this->actingAs($john)->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-port', '9993');

        // 2-FA without device
        $john->setSettings(
            [
                '2fa_enabled' => true,
            ]
        );
        \App\CompanionApp::where('user_id', $john->id)->delete();

        $response = $this->actingAs($john)->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'NO');

        // 2-FA with accepted auth attempt
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($john, "127.0.0.1");
        $authAttempt->accept();
        $authAttempt->save();

        $response = $this->actingAs($john)->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
    }
}
