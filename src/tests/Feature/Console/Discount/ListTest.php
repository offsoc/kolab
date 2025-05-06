<?php

namespace Tests\Feature\Console\Discount;

use Tests\TestCase;

class ListTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('discounts')
            ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
