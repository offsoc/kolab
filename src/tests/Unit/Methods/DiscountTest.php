<?php

namespace Tests\Unit\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Discount methods.
 */
class DiscountTest extends TestCase
{
    private $discount;

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->discount = new \App\Discount();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    public function testSetDiscountAttributeDouble()
    {
        $this->discount->discount = (double)1.01;

        $this->assertIsInt($this->discount->discount);
    }

    public function testSetDiscountAttributeFloat()
    {
        $this->discount->discount = (float)1.01;

        $this->assertIsInt($this->discount->discount);
    }

    /**
     * Test setting discount value
     */
    public function testDiscountValueLessThanZero()
    {
        $this->discount->discount = -1;

        $this->assertTrue($this->discount->discount == 0);
    }

    /**
     * Test setting discount value
     */
    public function testDiscountValueMoreThanHundred()
    {
        $this->discount->discount = 101;

        $this->assertTrue($this->discount->discount == 100);
    }
}
