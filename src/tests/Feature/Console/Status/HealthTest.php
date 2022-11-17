<?php

namespace Tests\Feature\Console\Status\Health;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * Test the command
     *
     * @group ldap
     * @group imap
     * @group meet
     * @group mollie
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("status:health");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
    }
}
