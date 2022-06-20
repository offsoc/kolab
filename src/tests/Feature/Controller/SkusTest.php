<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\SkusController;
use App\Sku;
use App\Tenant;
use Tests\TestCase;

class SkusTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
        Sku::where('title', 'test')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');
        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
        Sku::where('title', 'test')->delete();

        parent::tearDown();
    }

    /**
     * Test fetching SKUs list
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/skus");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        // Create an sku for another tenant, to make sure it is not included in the result
        $nsku = Sku::create([
                'title' => 'test',
                'name' => 'Test',
                'description' => '',
                'active' => true,
                'cost' => 100,
                'handler_class' => 'App\Handlers\Mailbox',
        ]);
        $tenant = Tenant::whereNotIn('id', [\config('app.tenant_id')])->first();
        $nsku->tenant_id = $tenant->id;
        $nsku->save();

        $response = $this->actingAs($john)->get("api/v4/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(10, $json);

        $this->assertSame(100, $json[0]['prio']);
        $this->assertSame($sku->id, $json[0]['id']);
        $this->assertSame($sku->title, $json[0]['title']);
        $this->assertSame($sku->name, $json[0]['name']);
        $this->assertSame($sku->description, $json[0]['description']);
        $this->assertSame($sku->cost, $json[0]['cost']);
        $this->assertSame($sku->units_free, $json[0]['units_free']);
        $this->assertSame($sku->period, $json[0]['period']);
        $this->assertSame($sku->active, $json[0]['active']);
        $this->assertSame('user', $json[0]['type']);
        $this->assertSame('Mailbox', $json[0]['handler']);

        // Test the type filter, and nextCost property (user with one domain)
        $response = $this->actingAs($john)->get("api/v4/skus?type=domain");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('domain-hosting', $json[0]['title']);
        $this->assertSame(100, $json[0]['nextCost']); // second domain costs 100

        // Test the type filter, and nextCost property (user with no domain)
        $jane = $this->getTestUser('jane@kolabnow.com');
        $kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $jane->assignPackage($kolab);

        $response = $this->actingAs($jane)->get("api/v4/skus?type=domain");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('domain-hosting', $json[0]['title']);
        $this->assertSame(0, $json[0]['nextCost']); // first domain costs 0
    }
}
