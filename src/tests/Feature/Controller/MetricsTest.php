<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

class MetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useServicesUrl();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test webhook
     */
    public function testStatus(): void
    {
        $response = $this->get("api/webhooks/metrics");
        $response->assertStatus(200);

        $body = $response->content();
        $this->assertTrue(str_contains($body, 'kolab'));
    }
}
