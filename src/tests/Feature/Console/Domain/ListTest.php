<?php

namespace Tests\Feature\Console\Domain;

use Tests\TestCase;

class ListTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $domain1 = \App\Domain::where('namespace', 'kolab.org')->first();
        $domain2 = \App\Domain::whereNot('tenant_id', $domain1->tenant_id)->first();

        // List domains for a specified tenant
        $code = \Artisan::call("domains --tenant={$domain1->tenant_id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString((string) $domain1->id, $output);
        $this->assertStringNotContainsString((string) $domain2->id, $output);

        // List domains of all tenants
        $code = \Artisan::call("domains");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString((string) $domain1->id, $output);
        $this->assertStringContainsString((string) $domain2->id, $output);

        // TODO: Test --with-deleted argument
        // TODO: Test output format and other attributes
        $this->markTestIncomplete();
    }
}
