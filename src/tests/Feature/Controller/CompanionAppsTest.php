<?php

namespace Tests\Feature\Controller;

use App\User;
use App\CompanionApp;
use Laravel\Passport\Token;
use Laravel\Passport\TokenRepository;
use Tests\TestCase;

class CompanionAppsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('CompanionAppsTest1@userscontroller.com');
        $this->deleteTestUser('CompanionAppsTest2@userscontroller.com');
        $this->deleteTestCompanionApp('testdevice');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('CompanionAppsTest1@userscontroller.com');
        $this->deleteTestUser('CompanionAppsTest2@userscontroller.com');
        $this->deleteTestCompanionApp('testdevice');

        parent::tearDown();
    }

    /**
     * Test registering the app
     */
    public function testRegister(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $notificationToken = "notificationToken";
        $deviceId = "deviceId";
        $name = "testname";

        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId, 'name' => $name]
        );

        $response->assertStatus(200);

        $companionApp = \App\CompanionApp::where('device_id', $deviceId)->first();
        $this->assertTrue($companionApp != null);
        $this->assertEquals($deviceId, $companionApp->device_id);
        $this->assertEquals($name, $companionApp->name);
        $this->assertEquals($notificationToken, $companionApp->notification_token);

        // Test a token update
        $notificationToken = "notificationToken2";
        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId, 'name' => $name]
        );

        $response->assertStatus(200);

        $companionApp->refresh();
        $this->assertEquals($notificationToken, $companionApp->notification_token);

        // Failing input valdiation
        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            []
        );
        $response->assertStatus(422);

        // Other users device
        $user2 = $this->getTestUser('CompanionAppsTest2@userscontroller.com');
        $response = $this->actingAs($user2)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId, 'name' => $name]
        );
        $response->assertStatus(403);
    }

    public function testIndex(): void
    {
        $response = $this->get("api/v4/companion");
        $response->assertStatus(401);

        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $user,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 1,
                'name' => 'testname',
            ]
        );

        $response = $this->actingAs($user)->get("api/v4/companion");
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['user_id']);
        $this->assertSame($companionApp['device_id'], $json['list'][0]['device_id']);
        $this->assertSame($companionApp['name'], $json['list'][0]['name']);
        $this->assertSame($companionApp['notification_token'], $json['list'][0]['notification_token']);
        $this->assertSame($companionApp['mfa_enabled'], $json['list'][0]['mfa_enabled']);

        $user2 = $this->getTestUser('CompanionAppsTest2@userscontroller.com');
        $response = $this->actingAs($user2)->get(
            "api/v4/companion"
        );
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }

    public function testShow(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');
        $companionApp = $this->getTestCompanionApp('testdevice', $user);

        $response = $this->get("api/v4/companion/{$companionApp->id}");
        $response->assertStatus(401);

        $response = $this->actingAs($user)->get("api/v4/companion/aaa");
        $response->assertStatus(404);

        $response = $this->actingAs($user)->get("api/v4/companion/{$companionApp->id}");
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame($companionApp->id, $json['id']);

        $user2 = $this->getTestUser('CompanionAppsTest2@userscontroller.com');
        $response = $this->actingAs($user2)->get("api/v4/companion/{$companionApp->id}");
        $response->assertStatus(403);
    }

    public function testPairing(): void
    {
        $response = $this->get("api/v4/companion/pairing");
        $response->assertStatus(401);

        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');
        $response = $this->actingAs($user)->get("api/v4/companion/pairing");
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertArrayHasKey('qrcode', $json);
        $this->assertSame('data:image/svg+xml;base64,', substr($json['qrcode'], 0, 26));
    }

    public function testRevoke(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');
        $companionApp = $this->getTestCompanionApp('testdevice', $user);
        $clientIdentifier = \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_id');

        $tokenRepository = app(TokenRepository::class);
        $tokenRepository->create([
            'id' => 'testtoken',
            'revoked' => false,
            'user_id' => $user->id,
            'client_id' => $clientIdentifier
        ]);

        //Make sure we have a token to revoke
        $tokenCount = Token::where('user_id', $user->id)->where('client_id', $clientIdentifier)->count();
        $this->assertTrue($tokenCount > 0);

        $response = $this->post("api/v4/companion/revoke");
        $response->assertStatus(401);

        $response = $this->actingAs($user)->post("api/v4/companion/revoke");
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('message', $json);

        $companionApp = \App\CompanionApp::where('device_id', 'testdevice')->first();
        $this->assertTrue($companionApp == null);

        $tokenCount = Token::where('user_id', $user->id)
            ->where('client_id', $clientIdentifier)
            ->where('revoked', false)->count();
        $this->assertSame(0, $tokenCount);
    }
}
