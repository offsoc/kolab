<?php

namespace Tests\Feature\Console\Plan;

use Tests\TestCase;

class PackagesTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('plan:packages')
            ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
