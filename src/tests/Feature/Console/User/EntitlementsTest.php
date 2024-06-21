<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class EntitlementsTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:entitlements unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $code = \Artisan::call("user:entitlements john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, "storage: 5") !== false);
        $this->assertTrue(strpos($output, "mailbox: 1") !== false);
        $this->assertTrue(strpos($output, "groupware: 1") !== false);
    }
}
