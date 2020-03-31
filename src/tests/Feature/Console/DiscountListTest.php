<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class DiscountListTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('discount:list')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
