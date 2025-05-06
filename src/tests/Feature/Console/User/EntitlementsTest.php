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
        $this->assertTrue(str_contains($output, "storage: 5"));
        $this->assertTrue(str_contains($output, "mailbox: 1"));
        $this->assertTrue(str_contains($output, "groupware: 1"));
    }
}
