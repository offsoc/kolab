<?php

namespace Tests\Feature\Controller\Reseller;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
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
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/resources");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/resources");
        $response->assertStatus(403);

        // Search with no search criteria
        $response = $this->actingAs($reseller1)->get("api/v4/resources");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search with no matches expected
        $response = $this->actingAs($reseller1)->get("api/v4/resources?search=john@kolab.org");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Search by email
        $response = $this->actingAs($reseller1)->get("api/v4/resources?search={$resource->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($resource->email, $json['list'][0]['email']);

        // Search by owner
        $response = $this->actingAs($reseller1)->get("api/v4/resources?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame($resource->email, $json['list'][0]['email']);
        $this->assertSame($resource->name, $json['list'][0]['name']);

        // Search by owner (Ned is a controller on John's wallets,
        // here we expect only resources assigned to Ned's wallet(s))
        $ned = $this->getTestUser('ned@kolab.org');
        $response = $this->actingAs($reseller1)->get("api/v4/resources?owner={$ned->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);

        $response = $this->actingAs($reseller2)->get("api/v4/resources?search={$resource->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        $response = $this->actingAs($reseller2)->get("api/v4/resources?owner={$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
    }

    /**
     * Test fetching resource info (GET /api/v4/resources/<resource-id>)
     */
    public function testShow(): void
    {
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $user = $this->getTestUser('john@kolab.org');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // Only resellers can access it
        $response = $this->actingAs($user)->get("api/v4/resources/{$resource->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get("api/v4/resources/{$resource->id}");
        $response->assertStatus(403);

        $response = $this->actingAs($reseller2)->get("api/v4/resources/{$resource->id}");
        $response->assertStatus(404);

        $response = $this->actingAs($reseller1)->get("api/v4/resources/{$resource->id}");
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

        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $resource = $this->getTestResource('resource-test1@kolab.org');

        // This end-point does not exist for resources
        $response = $this->actingAs($reseller1)->get("/api/v4/resources/{$resource->id}/status");
        $response->assertStatus(404);
    }

    /**
     * Test resources creating (POST /api/v4/resources)
     */
    public function testStore(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));

        // Test unauthorized access to reseller API
        $response = $this->actingAs($user)->post("/api/v4/resources", []);
        $response->assertStatus(403);

        // Reseller or admin can't create resources
        $response = $this->actingAs($admin)->post("/api/v4/resources", []);
        $response->assertStatus(403);

        $response = $this->actingAs($reseller1)->post("/api/v4/resources", []);
        $response->assertStatus(404);
    }
}
