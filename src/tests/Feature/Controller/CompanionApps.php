<?php

namespace Tests\Feature\Controller;

use App\User;
use App\CompanionApp;
use Tests\TestCase;

class CompanionAppsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestDomain('userscontroller.com');
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
     * Test registering the app
     */
    public function testRegister(): void
    {
        $user = $this->getTestUser('CompanionAppsTest1@userscontroller.com');

        $notificationToken = "notificationToken";
        $deviceId = "deviceId";

        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId]
        );

        $response->assertStatus(200);

        $companionApp = \App\CompanionApp::where('device_id', $deviceId)->first();
        $this->assertTrue($companionApp != null);
        $this->assertEquals($deviceId, $companionApp->device_id);
        $this->assertEquals($notificationToken, $companionApp->notification_token);

        // Test a token update
        $notificationToken = "notificationToken2";
        $response = $this->actingAs($user)->post(
            "api/v4/companion/register",
            ['notificationToken' => $notificationToken, 'deviceId' => $deviceId]
        );

        $response->assertStatus(200);

        $companionApp->refresh();
        $this->assertEquals($notificationToken, $companionApp->notification_token);
    }
}
