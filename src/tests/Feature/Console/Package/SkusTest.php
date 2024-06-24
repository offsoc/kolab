<?php

namespace Tests\Feature\Console\Package;

use Tests\TestCase;

class SkusTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('package:skus')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
