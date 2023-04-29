<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

/**
 * @group files
 */
class HealthTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
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
    public function testHealthProbes(): void
    {
        $response = $this->get("api/health/readiness");
        $response->assertStatus(200);
        $response = $this->get("api/health/liveness");
        $response->assertStatus(200);
    }

    public function testStatus(): void
    {
        $this->useServicesUrl();
        $response = $this->get("api/webhooks/health/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('ok', $json['status']);
        $this->assertTrue(array_key_exists('output', $json));
    }
}
