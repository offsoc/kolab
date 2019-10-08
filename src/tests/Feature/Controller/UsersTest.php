<?php

namespace Tests\Feature\Controller;

use App\User;

use Illuminate\Support\Str;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
        {@inheritDoc}

        @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $user = User::firstOrCreate(
            [
                'email' => 'UsersControllerTest1@UsersControllerTest.com'
            ]
        );

        $user->delete();
    }

    /**
        {@inheritDoc}

        @return void
     */
    public function tearDown(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UsersControllerTest1@UsersControllerTest.com'
            ]
        );

        $user->delete();

        parent::tearDown();
    }

    public function testListUsers()
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UsersControllerTest1@UsersControllerTest.com'
            ]
        );

        $response = $this->actingAs($user)->get("api/v4/users");

        $response->assertJsonCount(1);

        $response->assertStatus(200);
    }

    public function testUserEntitlements()
    {
        $userA = User::firstOrCreate(
            [
                'email' => 'UserEntitlement2A@UserEntitlement.com'
            ]
        );

        $response = $this->actingAs($userA, 'api')->get("/api/v4/users/{$userA->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $userA->id]);

        $user = factory(User::class)->create();
        $response = $this->actingAs($user)->get("/api/v4/users/{$userA->id}");
        $response->assertStatus(404);
    }
}
