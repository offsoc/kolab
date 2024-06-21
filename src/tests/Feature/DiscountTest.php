<?php

namespace Tests\Feature;

use App\Discount;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    /**
     * Test setting discount value
     */
    public function testDiscountValueLessThanZero(): void
    {
        $discount = new Discount();
        $discount->discount = -1;

        $this->assertTrue($discount->discount == 0);
    }

    /**
     * Test setting discount value
     */
    public function testDiscountValueMoreThanHundred(): void
    {
        $discount = new Discount();
        $discount->discount = 101;

        $this->assertTrue($discount->discount == 100);
    }
}
