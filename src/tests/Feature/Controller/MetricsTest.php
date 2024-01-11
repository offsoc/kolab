<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

/**
 * @group files
 */
class MetricsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->useServicesUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
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
