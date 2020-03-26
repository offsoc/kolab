<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\SkusController;
use App\Sku;
use Tests\TestCase;

class SkusTest extends TestCase
{
    /**
     * Test fetching SKUs list
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/skus");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');
        $sku = Sku::where('title', 'mailbox')->first();

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
     * Test for SkusController::skuElement()
     */
    public function testSkuElement(): void
    {
        $sku = Sku::where('title', 'storage')->first();
        $result = $this->invokeMethod(new SkusController(), 'skuElement', [$sku]);

        $this->assertSame($sku->id, $result['id']);
        $this->assertSame($sku->title, $result['title']);
        $this->assertSame($sku->name, $result['name']);
        $this->assertSame($sku->description, $result['description']);
        $this->assertSame($sku->cost, $result['cost']);
        $this->assertSame($sku->units_free, $result['units_free']);
        $this->assertSame($sku->period, $result['period']);
        $this->assertSame($sku->active, $result['active']);
        $this->assertSame('user', $result['type']);
        $this->assertSame('storage', $result['handler']);
        $this->assertSame($sku->units_free, $result['range']['min']);
        $this->assertSame($sku->handler_class::MAX_ITEMS, $result['range']['max']);
        $this->assertSame($sku->handler_class::ITEM_UNIT, $result['range']['unit']);
        $this->assertTrue($result['readonly']);
        $this->assertTrue($result['enabled']);

        // Test all SKU types
        $this->markTestIncomplete();
    }
}
