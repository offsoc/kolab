<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class ListTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        // Existing domain
        $code = \Artisan::call("users");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $this->assertTrue(str_contains($output, (string) $john->id));

        // TODO: Test --deleted argument
        // TODO: Test output format and other attributes
        // TODO: Test tenant context
        $this->markTestIncomplete();
    }
}
