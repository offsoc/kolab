<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class DomainsTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:domains unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $code = \Artisan::call("user:domains john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, "kolab.org") !== false);
        $this->assertTrue(strpos($output, \config('app.domain')) !== false);
    }
}
