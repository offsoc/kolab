<?php

namespace Tests\Feature\Controller\Admin;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test resources searching (/api/v4/resources)
     */
    public function testIndex(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/resources");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($admin)->get("api/v4/resources");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
        $this->assertSame("0 resources have been found.", $json['message']);

        // Search with no matches expected
        $response = $this->actingAs($admin)->get("api/v4/resources?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by email
        $response = $this->actingAs($admin)->get("api/v4/resources?search={$resource->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($resource->email, $json['list'][0]['email']);

        // Search by owner
        $response = $this->actingAs($admin)->get("api/v4/resources?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame("2 resources have been found.", $json['message']);
        $this->assertSame($resource->email, $json['list'][0]['email']);
        $this->assertSame($resource->name, $json['list'][0]['name']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only resources assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($admin)->get("api/v4/resources?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }

    /**
     * Test fetching resource info (GET /api/v4/resources/<resource-id>)
     */
    public function testShow(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // Only admins can access it
        $response = $this->actingAs($user)->get("api/v4/resources/{$resource->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/resources/{$resource->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($resource->id, $json['id']);
        $this->assertSame($resource->email, $json['email']);
        $this->assertSame($resource->name, $json['name']);
    }

    /**
     * Test fetching resource status (GET /api/v4/resources/<resource-id>/status)
     */
    public function testStatus(): void
    {
        Queue::fake(); // disable jobs

        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // This end-point does not exist for admins
        $response = $this->actingAs($admin)->get("/api/v4/resources/{$resource->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test resource creating (POST /api/v4/resources)
     */
    public function testStore(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');

        // Test unauthorized access to admin API
        $response = $this->actingAs($user)->post("/api/v4/resources", []);
        $response->assertStatus(403);

        // Admin can't create resources
        $response = $this->actingAs($admin)->post("/api/v4/resources", []);
        $response->assertStatus(404);
    }
}
