<?php

namespace Tests\Feature\Controller\Admin;

use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);

        parent::tearDown();
    }

    /**
     * Test users searching (/api/v4/users)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/users");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($admin)->get("api/v4/users");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($admin)->get("api/v4/users?search=abcd1234efgh5678");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by domain
        $response = $this->actingAs($admin)->get("api/v4/users?search=kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by user ID
        $response = $this->actingAs($admin)->get("api/v4/users?search={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (primary)
        $response = $this->actingAs($admin)->get("api/v4/users?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (alias)
        $response = $this->actingAs($admin)->get("api/v4/users?search=john.doe@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($user->id, $json['list'][0]['id']);
        $this->assertSame($user->email, $json['list'][0]['email']);

        // Search by email (external), expect two users in a result
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', 'john.doe.external@gmail.com');

        $response = $this->actingAs($admin)->get("api/v4/users?search=john.doe.external@gmail.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);

        $emails = array_column($json['list'], 'email');

        $this->assertContains($user->email, $emails);
        $this->assertContains($jack->email, $emails);
    }
}
