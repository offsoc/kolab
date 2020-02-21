<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class PlanPackagesTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('plan:packages')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
