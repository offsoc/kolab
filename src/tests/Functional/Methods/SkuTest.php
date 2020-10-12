<?php

namespace Tests\Functional\Methods;

use Tests\TestCase;

class SkuTest extends TestCase
{
    // not a method, but a property test...
    public function testSkuHandlerClass()
    {
        $skus = \App\Sku::all();

        foreach ($skus as $sku) {
            $this->assertNotNull($sku->handler_class);
            $this->assertTrue(method_exists($sku->handler_class, 'preReq'));
        }
    }
}
