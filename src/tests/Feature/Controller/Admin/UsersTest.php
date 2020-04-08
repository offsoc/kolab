<?php

namespace Tests\Feature\Controller\Admin;

use App\Domain;
use App\User;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // This will set base URL for all tests in this file
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url
        \config(['app.url' => str_replace('//', '//admin.', \config('app.url'))]);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test (/api/v4/index)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        $response = $this->actingAs($user)->get("api/v4/users");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/users");
        $response->assertStatus(200);

        // TODO: Test the response
        $this->markTestIncomplete();
    }
}
