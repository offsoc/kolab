<?php

namespace Tests\Feature\Controller;

use App\User;
use App\CompanionApp;
use Laravel\Passport\Token;
use Laravel\Passport\Passport;
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
     * Test creating the app
     */
    public function testStore(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $name = "testname";

        $post = ['name' => $name];
        $response = $this->actingAs($user)->post("api/v4/companions", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Companion app has been created.", $json['message']);

        $companionApp = \App\CompanionApp::where('name', $name)->first();
        $this->assertTrue($companionApp != null);
        $this->assertEquals($name, $companionApp->name);
        $this->assertFalse((bool)$companionApp->mfa_enabled);
    }

    /**
     * Test destroying the app
     */
    public function testDestroy(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');
        $user2 = $this->getTestUser('CompanionAppsTest2@userscontroller.com');

        $response = $this->actingAs($user)->delete("api/v4/companions/foobar");
        $response->assertStatus(404);

        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $user,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 1,
                'name' => 'testname',
            ]
        );

        $client = Passport::client()->forceFill([
            'user_id' => $user->id,
            'name' => "CompanionApp Password Grant Client",
            'secret' => "VerySecret",
            'provider' => 'users',
            'redirect' => 'https://' . \config('app.website_domain'),
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
            'allowed_scopes' => ["mfa"]
        ]);
        print(var_export($client, true));
        $client->save();
        $companionApp->oauth_client_id = $client->id;
        $companionApp->save();

        $tokenRepository = app(TokenRepository::class);
        $tokenRepository->create([
            'id' => 'testtoken',
            'revoked' => false,
            'user_id' => $user->id,
            'client_id' => $client->id
        ]);

        //Make sure we have a token to revoke
        $tokenCount = Token::where('user_id', $user->id)->where('client_id', $client->id)->count();
        $this->assertTrue($tokenCount > 0);


        $response = $this->actingAs($user2)->delete("api/v4/companions/{$companionApp->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($user)->delete("api/v4/companions/{$companionApp->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Companion app has been removed.", $json['message']);

        $client->refresh();
        $this->assertSame((bool)$client->revoked, true);

        $companionApp = \App\CompanionApp::where('device_id', 'testdevice')->first();
        $this->assertTrue($companionApp == null);

        $tokenCount = Token::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->where('revoked', false)->count();
        $this->assertSame(0, $tokenCount);
    }


    /**
     * Test listing apps
     */
    public function testIndex(): void
    {
        $response = $this->get("api/v4/companions");
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

        $response = $this->actingAs($user)->get("api/v4/companions");
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
            "api/v4/companions"
        );
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }


    /**
     * Test showing the app
     */
    public function testShow(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');
        $companionApp = $this->getTestCompanionApp('testdevice', $user);

        $response = $this->get("api/v4/companions/{$companionApp->id}");
        $response->assertStatus(401);

        $response = $this->actingAs($user)->get("api/v4/companions/aaa");
        $response->assertStatus(404);

        $response = $this->actingAs($user)->get("api/v4/companions/{$companionApp->id}");
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame($companionApp->id, $json['id']);

        $user2 = $this->getTestUser('CompanionAppsTest2@userscontroller.com');
        $response = $this->actingAs($user2)->get("api/v4/companions/{$companionApp->id}");
        $response->assertStatus(403);
    }


    /**
     * Test registering the app
     */
    public function testRegister(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $user,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 0,
                'name' => 'testname',
            ]
        );

        $notificationToken = "notificationToken";
        $deviceId = "deviceId";
        $name = "testname";

        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            [
                'notificationToken' => $notificationToken,
                'deviceId' => $deviceId,
                'name' => $name,
                'companionId' => $companionApp->id
            ]
        );

        $response->assertStatus(200);

        $companionApp->refresh();
        $this->assertTrue($companionApp != null);
        $this->assertEquals($deviceId, $companionApp->device_id);
        $this->assertEquals($name, $companionApp->name);
        $this->assertEquals($notificationToken, $companionApp->notification_token);
        $this->assertTrue((bool)$companionApp->mfa_enabled);

        // Companion id required
        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId, 'name' => $name]
        );
        $response->assertStatus(422);

        // Test a token update
        $notificationToken = "notificationToken2";
        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            [
                'notificationToken' => $notificationToken,
                'deviceId' => $deviceId,
                'name' => $name,
                'companionId' => $companionApp->id
            ]
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
            [
                'notificationToken' => $notificationToken,
                'deviceId' => $deviceId,
                'name' => $name,
                'companionId' => $companionApp->id
            ]
        );
        $response->assertStatus(403);
    }


    /**
     * Test getting the pairing info
     */
    public function testPairing(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $companionApp = $this->getTestCompanionApp(
            'testdevice',
            $user,
            [
                'notification_token' => 'notificationtoken',
                'mfa_enabled' => 0,
                'name' => 'testname',
            ]
        );

        $response = $this->get("api/v4/companions/{$companionApp->id}/pairing");
        $response->assertStatus(401);

        $response = $this->actingAs($user)->get("api/v4/companions/{$companionApp->id}/pairing");
        $response->assertStatus(200);

        $companionApp->refresh();
        $this->assertTrue($companionApp->oauth_client_id != null);

        $json = $response->json();
        $this->assertArrayHasKey('qrcode', $json);
        $this->assertSame('data:image/svg+xml;base64,', substr($json['qrcode'], 0, 26));
    }
}
