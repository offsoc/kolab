<?php

namespace Tests\Feature\Console\Domain;

use Tests\TestCase;

class StatusTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        // Non-existing domain
        $code = \Artisan::call("domain:status unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);


        // Existing domain
        $code = \Artisan::call("domain:status kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $this->assertTrue(strpos($output, "In total: 114") !== false);

        // TODO: More precise output testing
    }
}
