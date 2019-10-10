<?php

namespace Tests\Feature\Controller;

use App\User;

use Illuminate\Support\Str;
use Tests\TestCase;

class SignupTest extends TestCase
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
                'email' => 'SignupControllerTest1@SignupControllerTest.com'
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
                'email' => 'SignupControllerTest1@SignupControllerTest.com'
            ]
        );

        $user->delete();

        parent::tearDown();
    }

    public function testRegisterUser()
    {
        $data = [
            'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com',
            'password' => 'simple123',
            'password_confirmation' => 'simple123'
        ];

        $response = $this->post('/api/auth/register', $data);
        $response->assertStatus(200);
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
}
