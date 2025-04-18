<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

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
}
