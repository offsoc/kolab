<?php

namespace Tests\Feature\Controller;

use App\License;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $user = $this->getTestUser('john@kolab.org');
        $user->licenses()->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->licenses()->delete();

        parent::tearDown();
    }

    /**
     * Test fetching licenses for a user (GET /users/<id>/licenses/<type>)
     */
    public function testLicenses(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/license/test");
        $response->assertStatus(401);

        $license = License::create([
                'key' => (string) microtime(true),
                'type' => 'test',
                'tenant_id' => $user->tenant_id,
        ]);

        // Unknow type
        $response = $this->actingAs($user)->get("api/v4/license/unknown");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(0, $json['list']);
        $this->assertSame(0, $json['count']);
        $this->assertFalse($json['hasMore']);

        // Valid type, existing license - expect license assignment
        $response = $this->actingAs($user)->get("api/v4/license/test");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json['list']);
        $this->assertSame(1, $json['count']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($license->key, $json['list'][0]['key']);
        $this->assertSame($license->type, $json['list'][0]['type']);

        $license->refresh();
        $this->assertEquals($user->id, $license->user_id);

        // Try again with assigned license
        $response = $this->actingAs($user)->get("api/v4/license/test");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json['list']);
        $this->assertSame(1, $json['count']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($license->key, $json['list'][0]['key']);
        $this->assertSame($license->type, $json['list'][0]['type']);
        $this->assertEquals($user->id, $license->user_id);
    }
}
