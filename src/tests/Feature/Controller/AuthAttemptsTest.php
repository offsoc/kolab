<?php

namespace Tests\Feature\Controller;

use App\User;
use App\AuthAttempt;
use Tests\TestCase;

class AuthAttemptsTest extends TestCase
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

    public function testRecord(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertEquals($authAttempt->user_id, $user->id);
        $this->assertEquals($authAttempt->ip, "10.0.0.1");
        $authAttempt->refresh();
        $this->assertEquals($authAttempt->status, "NEW");

        $authAttempt2 = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertEquals($authAttempt->id, $authAttempt2->id);

        $authAttempt3 = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.2");
        $this->assertNotEquals($authAttempt->id, $authAttempt3->id);
    }


    public function testAcceptDeny(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");

        $response = $this->actingAs($user)->post("api/v4/auth-attempts/{$authAttempt->id}/confirm");
        $response->assertStatus(200);
        $authAttempt->refresh();
        $this->assertTrue($authAttempt->isAccepted());

        $response = $this->actingAs($user)->post("api/v4/auth-attempts/{$authAttempt->id}/deny");
        $response->assertStatus(200);
        $authAttempt->refresh();
        $this->assertTrue($authAttempt->isDenied());
    }


    public function testDetails(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");

        $response = $this->actingAs($user)->get("api/v4/auth-attempts/{$authAttempt->id}/details");
        $response->assertStatus(200);

        $json = $response->json();

        $authAttempt->refresh();

        $this->assertEquals($user->email, $json['username']);
        $this->assertEquals($authAttempt->ip, $json['ip']);
        $this->assertEquals(json_encode($authAttempt->updated_at), "\"" . $json['timestamp'] . "\"");
        $this->assertEquals("CH", $json['country']);
    }


    public function testList(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $authAttempt2 = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.2");

        $response = $this->actingAs($user)->get("api/v4/auth-attempts");
        $response->assertStatus(200);

        $json = $response->json();

        /* var_export($json); */

        $this->assertEquals(count($json), 2);
        $this->assertEquals($json[0]['id'], $authAttempt->id);
        $this->assertEquals($json[1]['id'], $authAttempt2->id);
    }
}
