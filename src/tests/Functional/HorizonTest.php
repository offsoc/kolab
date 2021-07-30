<?php

namespace Tests\Functional;

use Tests\TestCase;

class HorizonTest extends TestCase
{
    public function testAdminAccess()
    {
        $this->useAdminUrl();

        $response = $this->get('horizon/dashboard');

        $response->assertStatus(200);
    }

    /*
    public function testRegularAccess()
    {
        $this->useRegularUrl();

        $response = $this->get('horizon/dashboard');

        $response->assertStatus(404);
    }
    */
}
