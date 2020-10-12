<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Discount methods where additional infrastructure is required, such as Redis and
 * MariaDB.
 */
class DiscountTest extends TestCase
{
    public function testWallets()
    {
        $this->markTestIncomplete();
    }
}
