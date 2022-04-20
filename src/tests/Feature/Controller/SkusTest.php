<?php

namespace Tests\Feature\Controller;

use App\Entitlement;
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

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
        Sku::where('title', 'test')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
        Sku::where('title', 'test')->delete();

        parent::tearDown();
    }

    /**
     * Test fetching SKUs list for a domain (GET /domains/<id>/skus)
     */
    public function testDomainSkus(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $domain = $this->getTestDomain('kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/domains/{$domain->id}/skus");
        $response->assertStatus(401);

        // Create an sku for another tenant, to make sure it is not included in the result
        $nsku = Sku::create([
                'title' => 'test',
                'name' => 'Test',
                'description' => '',
                'active' => true,
                'cost' => 100,
                'handler_class' => 'App\Handlers\Domain',
        ]);
        $tenant = Tenant::whereNotIn('id', [\config('app.tenant_id')])->first();
        $nsku->tenant_id = $tenant->id;
        $nsku->save();

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);

        $this->assertSkuElement('domain-hosting', $json[0], [
                'prio' => 0,
                'type' => 'domain',
                'handler' => 'DomainHosting',
                'enabled' => false,
                'readonly' => false,
        ]);
    }

    /**
     * Test fetching SKUs list
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/skus");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
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

        $response = $this->actingAs($user)->get("api/v4/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(14, $json);

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
    }

    /**
     * Test fetching SKUs list for a user (GET /users/<id>/skus)
     */
    public function testUserSkus(): void
    {
        $user = $this->getTestUser('john@kolab.org');

        // Unauth access not allowed
        $response = $this->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(401);

        // Create an sku for another tenant, to make sure it is not included in the result
        $nsku = Sku::create([
                'title' => 'test',
                'name' => 'Test',
                'description' => '',
                'active' => true,
                'cost' => 100,
                'handler_class' => 'Mailbox',
        ]);
        $tenant = Tenant::whereNotIn('id', [\config('app.tenant_id')])->first();
        $nsku->tenant_id = $tenant->id;
        $nsku->save();

        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(6, $json);

        $this->assertSkuElement('mailbox', $json[0], [
                'prio' => 100,
                'type' => 'user',
                'handler' => 'Mailbox',
                'enabled' => true,
                'readonly' => true,
        ]);

        $this->assertSkuElement('storage', $json[1], [
                'prio' => 90,
                'type' => 'user',
                'handler' => 'Storage',
                'enabled' => true,
                'readonly' => true,
                'range' => [
                    'min' => 5,
                    'max' => 100,
                    'unit' => 'GB',
                ]
        ]);

        $this->assertSkuElement('groupware', $json[2], [
                'prio' => 80,
                'type' => 'user',
                'handler' => 'Groupware',
                'enabled' => false,
                'readonly' => false,
        ]);

        $this->assertSkuElement('activesync', $json[3], [
                'prio' => 70,
                'type' => 'user',
                'handler' => 'Activesync',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Groupware'],
        ]);

        $this->assertSkuElement('2fa', $json[4], [
                'prio' => 60,
                'type' => 'user',
                'handler' => 'Auth2F',
                'enabled' => false,
                'readonly' => false,
                'forbidden' => ['Activesync'],
        ]);

        $this->assertSkuElement('meet', $json[5], [
                'prio' => 50,
                'type' => 'user',
                'handler' => 'Meet',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Groupware'],
        ]);

        // Test inclusion of beta SKUs
        $sku = Sku::withEnvTenantContext()->where('title', 'beta')->first();
        $user->assignSku($sku);
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(11, $json);

        $this->assertSkuElement('beta', $json[6], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'Beta',
                'enabled' => false,
                'readonly' => false,
        ]);

        $this->assertSkuElement('beta-distlists', $json[7], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'Beta\Distlists',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Beta'],
        ]);

        $this->assertSkuElement('beta-resources', $json[8], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'Beta\Resources',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Beta'],
        ]);

        $this->assertSkuElement('beta-shared-folders', $json[9], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'Beta\SharedFolders',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Beta'],
        ]);

        $this->assertSkuElement('files', $json[10], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'Files',
                'enabled' => false,
                'readonly' => false,
                'required' => ['Beta'],
        ]);
    }

    /**
     * Assert content of the SKU element in an API response
     *
     * @param string $sku_title The SKU title
     * @param array  $result    The result to assert
     * @param array  $other     Other items the SKU itself does not include
     */
    protected function assertSkuElement($sku_title, $result, $other = []): void
    {
        $sku = Sku::withEnvTenantContext()->where('title', $sku_title)->first();

        $this->assertSame($sku->id, $result['id']);
        $this->assertSame($sku->title, $result['title']);
        $this->assertSame($sku->name, $result['name']);
        $this->assertSame($sku->description, $result['description']);
        $this->assertSame($sku->cost, $result['cost']);
        $this->assertSame($sku->units_free, $result['units_free']);
        $this->assertSame($sku->period, $result['period']);
        $this->assertSame($sku->active, $result['active']);

        foreach ($other as $key => $value) {
            $this->assertSame($value, $result[$key]);
        }

        $this->assertCount(8 + count($other), $result);
    }
}
