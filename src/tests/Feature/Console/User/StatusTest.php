<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class StatusTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:status unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame(
            "User not found.\nTry ./artisan scalpel:user:read --attr=email --attr=tenant_id unknown",
            $output
        );

        $code = \Artisan::call("user:status john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Status: active,ldapReady,imapReady", $output);
    }
}
