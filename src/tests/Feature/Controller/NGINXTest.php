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
        $john->setSettings([
                'limit_geo' => null,
                'guam_enabled' => null,
        ]);
        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();

        $this->useServicesUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        \App\CompanionApp::where('user_id', $john->id)->delete();
        \App\AuthAttempt::where('user_id', $john->id)->delete();
        $john->setSettings([
                'limit_geo' => null,
                'guam_enabled' => null,
        ]);
        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();

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
        $response->assertHeader('auth-port', \config('imap.imap_port'));

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
        $response->assertHeader('auth-server', gethostbyname(\config('smtp.host')));
        $response->assertHeader('auth-port', \config('smtp.port'));
        $response->assertHeader('auth-pass', $pass);

        // Empty Auth Protocol
        $modifiedHeaders = $headers;
        $modifiedHeaders['Auth-Protocol'] = "";
        $response = $this->withHeaders($modifiedHeaders)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');


        // Guam
        $john->setSettings(['guam_enabled' => 'true']);

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');
        $response->assertHeader('auth-server', gethostbyname(\config('imap.host')));
        $response->assertHeader('auth-port', \config('imap.guam_port'));


        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $john,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 1,
                'name' => 'testname',
            ]
        );

        // 2-FA with accepted auth attempt
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($john, "127.0.0.1");
        $authAttempt->accept();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');

        // Deny
        $authAttempt->deny();
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        // 2-FA without device
        $companionApp->delete();
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');


        // Geo-lockin (failure)
        $john->setSettings(['limit_geo' => '["PL","US"]']);

        $headers['Auth-Protocol'] = 'imap';
        $headers['Client-Ip'] = '127.0.0.1';

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'authentication failure');

        $authAttempt = \App\AuthAttempt::where('ip', $headers['Client-Ip'])->where('user_id', $john->id)->first();
        $this->assertSame('geolocation', $authAttempt->reason);
        \App\AuthAttempt::where('user_id', $john->id)->delete();

        // Geo-lockin (success)
        \App\IP4Net::create([
                'net_number' => '127.0.0.0',
                'net_broadcast' => '127.255.255.255',
                'net_mask' => 8,
                'country' => 'US',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx");
        $response->assertStatus(200);
        $response->assertHeader('auth-status', 'OK');

        $this->assertCount(0, \App\AuthAttempt::where('user_id', $john->id)->get());
    }

    /**
     * Test the httpauth webhook
     */
    public function testNGINXHttpAuthHook(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $response = $this->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);

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
        $response->assertStatus(200);

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

        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $john,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 1,
                'name' => 'testname',
            ]
        );

        // 2-FA with accepted auth attempt
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($john, "127.0.0.1");
        $authAttempt->accept();

        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);

        // Deny
        $authAttempt->deny();
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(403);

        // 2-FA without device
        $companionApp->delete();
        $response = $this->withHeaders($headers)->get("api/webhooks/nginx-httpauth");
        $response->assertStatus(200);
    }
}
