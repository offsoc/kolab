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
        $this->expectException(\Exception::class);

        $discount = new Discount();
        $discount->discount = -1;
    }

    /**
     * Test setting discount value
     */
    public function testDiscountValueMoreThanHundred(): void
    {
        $this->expectException(\Exception::class);

        $discount = new Discount();
        $discount->discount = 101;
    }
}
