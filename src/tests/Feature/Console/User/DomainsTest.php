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
        $this->assertSame("No such user unknown", $output);

        $code = \Artisan::call("user:domains john@kolab.org --attr=namespace");
        $output = trim(\Artisan::output());

        $domain = $this->getTestDomain('kolab.org');

        $this->assertSame(0, $code);
        $this->assertSame("{$domain->id} {$domain->namespace}", $output);
    }
}
