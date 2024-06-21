<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user-settings");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);

        // Test the output and extra arguments
        $this->markTestIncomplete();
    }
}
