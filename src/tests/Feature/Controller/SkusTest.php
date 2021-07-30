<?php

namespace Tests\Feature\Controller;

use App\Entitlement;
use App\Http\Controllers\API\V4\SkusController;
use App\Sku;
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
        $nsku->tenant_id = 2;
        $nsku->save();

        $response = $this->actingAs($user)->get("api/v4/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(9, $json);

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
        $this->assertSame('mailbox', $json[0]['handler']);
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
                'handler_class' => 'App\Handlers\Mailbox',
        ]);
        $nsku->tenant_id = 2;
        $nsku->save();

        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(8, $json);

        $this->assertSkuElement('mailbox', $json[0], [
                'prio' => 100,
                'type' => 'user',
                'handler' => 'mailbox',
                'enabled' => true,
                'readonly' => true,
        ]);

        $this->assertSkuElement('storage', $json[1], [
                'prio' => 90,
                'type' => 'user',
                'handler' => 'storage',
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
                'handler' => 'groupware',
                'enabled' => false,
                'readonly' => false,
        ]);

        $this->assertSkuElement('activesync', $json[3], [
                'prio' => 70,
                'type' => 'user',
                'handler' => 'activesync',
                'enabled' => false,
                'readonly' => false,
                'required' => ['groupware'],
        ]);

        $this->assertSkuElement('2fa', $json[4], [
                'prio' => 60,
                'type' => 'user',
                'handler' => 'auth2f',
                'enabled' => false,
                'readonly' => false,
                'forbidden' => ['activesync'],
        ]);

        $this->assertSkuElement('meet', $json[5], [
                'prio' => 50,
                'type' => 'user',
                'handler' => 'meet',
                'enabled' => false,
                'readonly' => false,
                'required' => ['groupware'],
        ]);

        $this->assertSkuElement('domain-hosting', $json[6], [
                'prio' => 0,
                'type' => 'domain',
                'handler' => 'domainhosting',
                'enabled' => false,
                'readonly' => false,
        ]);

        $this->assertSkuElement('group', $json[7], [
                'prio' => 0,
                'type' => 'group',
                'handler' => 'group',
                'enabled' => false,
                'readonly' => false,
        ]);

        // Test filter by type
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus?type=domain");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('domain', $json[0]['type']);

        // Test inclusion of beta SKUs
        $sku = Sku::withEnvTenantContext()->where('title', 'beta')->first();
        $user->assignSku($sku);
        $response = $this->actingAs($user)->get("api/v4/users/{$user->id}/skus?type=user");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(8, $json);

        $this->assertSkuElement('meet', $json[5], [
                'prio' => 50,
                'type' => 'user',
                'handler' => 'meet',
                'enabled' => false,
                'readonly' => false,
                'required' => ['groupware'],
        ]);

        $this->assertSkuElement('beta', $json[6], [
                'prio' => 10,
                'type' => 'user',
                'handler' => 'beta',
                'enabled' => false,
                'readonly' => false,
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
