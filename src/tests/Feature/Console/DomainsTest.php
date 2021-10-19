<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class DomainsTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $domain = \App\Domain::where('namespace', 'kolab.org')->first();

        // Existing domain
        $code = \Artisan::call("domains");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $this->assertTrue(strpos($output, (string) $domain->id) !== false);

        // TODO: Test --deleted argument
        // TODO: Test output format and other attributes
        // TODO: Test tenant context
        $this->markTestIncomplete();
    }
}
