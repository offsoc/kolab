<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

class HorizonTest extends TestCase
{
    public function testAdminAccess()
    {
        if (!file_exists('public/vendor/horizon/mix-manifest.json')) {
            $this->markTestSkipped();
        }

        $this->useAdminUrl();

        $response = $this->get('horizon/dashboard');

        $response->assertStatus(200);
    }

    public function testRegularAccess()
    {
        if (!file_exists('public/vendor/horizon/mix-manifest.json')) {
            $this->markTestSkipped();
        }

        $this->useRegularUrl();

        $response = $this->get('horizon/dashboard');

        // TODO: We should make it 404
        $response->assertStatus(200);
    }
}
