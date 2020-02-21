<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class UserDomainsTest extends TestCase
{
    public function testHandle(): void
    {
        $this->artisan('user:domains john@kolab.org')
             ->assertExitCode(0);

        $this->markTestIncomplete();
    }
}
