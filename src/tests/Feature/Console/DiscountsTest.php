<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class DiscountsTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('discounts')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
