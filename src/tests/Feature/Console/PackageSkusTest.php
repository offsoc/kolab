<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class PackageSkusTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('package:skus')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
