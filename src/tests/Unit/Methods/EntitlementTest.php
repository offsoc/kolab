<?php

namespace Tests\Unit\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Entitlement methods.
 */
class EntitlementTest extends TestCase
{
    private $entitlement;

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->entitlement = new \App\Entitlement();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    /** TODO: This is a functional test because of the want for a discount on the associated wallet. */
    public function testCostsPerDay()
    {
        $this->markTestSkipped('This is a functional test');

        $daysInLastMonth = \App\Utils::daysInLastMonth();
        $this->entitlement->cost = $daysInLastMonth * 100;

        $this->assertEqual($this->entitlement->costsPerDay(), 100);
    }

    public function testCreateTransaction()
    {
        $this->markTestSkipped('This is a functional test');
    }

    public function testEntitleable()
    {
        $this->markTestSkipped('This is a functional test');
    }

    public function testEntitleableTitle()
    {
        $this->markTestSkipped('This is a functional test');
    }

    public function testSku()
    {
        $this->markTestSkipped('This is a functional test');
    }

    public function testWallet()
    {
        $this->markTestSkipped('This is a functional test');
    }

    public function testCost()
    {
        $this->entitlement->cost = 1000;
        $this->assertEqual($this->entitlement->cost, 1000);
    }

    public function testCostDouble()
    {
        $this->entitlement->cost = (double) 1000.49;
        $this->assertEqual($this->entitlement->cost, 1000);
    }

    public function testCostFloat()
    {
        $this->entitlement->cost = (float) 1000.49;
        $this->assertEqual($this->entitlement->cost, 1000);
    }

    public function testCostNegative()
    {
        $this->entitlement->cost = -1000;
        $this->assertEqual($this->entitlement->cost, -1000);
    }
}
