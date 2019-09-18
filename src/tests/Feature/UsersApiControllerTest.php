<?php

namespace Tests\Unit;

use App\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersApiControllerTest extends TestCase
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
                'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com'
            ]
        );

        $user->delete();
    }

    public function testRegisterUser()
    {
        $data = [
            'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com',
            'password' => 'simple123'
        ];

        $response = $this->post('/api/v4/users/register', $data);
        $response->assertStatus(201);
    }

    public function testListUsers()
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com'
            ]
        );

        $response = $this->actingAs($user)->get("api/v4/users");

        $response->assertJsonCount(1);

        $response->assertStatus(200);
    }

    /**
        {@inheritDoc}

        @return void
     */
    public function tearDown(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com'
            ]
        );

        $user->delete();

        parent::tearDown();
    }
}
