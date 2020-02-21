<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class UserEntitlementsTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('user:entitlements john@kolab.org')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
