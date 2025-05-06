<?php

namespace Tests\Feature\Controller;

use App\AuthAttempt;
use App\User;
use Tests\TestCase;

class AuthAttemptsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestDomain('userscontroller.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('UsersControllerTest1@userscontroller.com');
        $this->deleteTestDomain('userscontroller.com');

        parent::tearDown();
    }

    /**
     * Test cofirm (POST /api/v4/auth-attempts/<authAttempt>/confirm)
     */
    public function testAccept(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");

        $response = $this->actingAs($user)->post("api/v4/auth-attempts/{$authAttempt->id}/confirm");
        $response->assertStatus(200);
        $authAttempt->refresh();
        $this->assertTrue($authAttempt->isAccepted());

        // wrong user
        $user2 = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $response = $this->actingAs($user2)->post("api/v4/auth-attempts/{$authAttempt->id}/confirm");
        $response->assertStatus(403);

        // wrong id
        $response = $this->actingAs($user)->post("api/v4/auth-attempts/9999/confirm");
        $response->assertStatus(404);
    }

    /**
     * Test deny (POST /api/v4/auth-attempts/<authAttempt>/deny)
     */
    public function testDeny(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");

        $response = $this->actingAs($user)->post("api/v4/auth-attempts/{$authAttempt->id}/deny");
        $response->assertStatus(200);
        $authAttempt->refresh();
        $this->assertTrue($authAttempt->isDenied());

        // wrong user
        $user2 = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $response = $this->actingAs($user2)->post("api/v4/auth-attempts/{$authAttempt->id}/deny");
        $response->assertStatus(403);

        // wrong id
        $response = $this->actingAs($user)->post("api/v4/auth-attempts/9999/deny");
        $response->assertStatus(404);
    }

    /**
     * Test details (GET /api/v4/auth-attempts/<authAttempt>/details)
     */
    public function testDetails(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");

        $response = $this->actingAs($user)->get("api/v4/auth-attempts/{$authAttempt->id}/details");
        $response->assertStatus(200);

        $json = $response->json();

        $authAttempt->refresh();

        $this->assertSame($user->email, $json['username']);
        $this->assertSame($authAttempt->ip, $json['entry']['ip']);
        $this->assertSame(json_encode($authAttempt->updated_at), "\"" . $json['entry']['updated_at'] . "\"");
        $this->assertSame("CH", $json['country']);

        // wrong user
        $user2 = $this->getTestUser('UsersControllerTest2@userscontroller.com');
        $response = $this->actingAs($user2)->get("api/v4/auth-attempts/{$authAttempt->id}/details");
        $response->assertStatus(403);

        // wrong id
        $response = $this->actingAs($user)->get("api/v4/auth-attempts/9999/details");
        $response->assertStatus(404);
    }

    /**
     * Test list (GET /api/v4/auth-attempts)
     */
    public function testList(): void
    {
        $user = $this->getTestUser('UsersControllerTest1@userscontroller.com');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $authAttempt2 = AuthAttempt::recordAuthAttempt($user, "10.0.0.2");

        $response = $this->actingAs($user)->get("api/v4/auth-attempts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertTrue(in_array($json[0]['id'], [$authAttempt->id, $authAttempt2->id]));
        $this->assertTrue(in_array($json[1]['id'], [$authAttempt->id, $authAttempt2->id]));
        $this->assertTrue($json[0]['id'] != $json[1]['id']);
    }
}
