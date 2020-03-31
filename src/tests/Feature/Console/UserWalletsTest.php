<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class UserWalletsTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('user:wallets john@kolab.org')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
