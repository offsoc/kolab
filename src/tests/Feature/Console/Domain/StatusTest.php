<?php

namespace Tests\Feature\Console\Domain;

use App\Domain;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestDomain('domain-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing domain
        $code = \Artisan::call("domain:status unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        // Existing domain
        $code = \Artisan::call("domain:status kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        if (\config('app.with_ldap')) {
            $this->assertSame("Status (114): active (2), confirmed (16), verified (32), ldapReady (64)", $output);
        } else {
            $this->assertSame("Status (50): active (2), confirmed (16), verified (32)", $output);
        }

        // Test deleted domain
        $domain = $this->getTestDomain('domain-delete.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_HOSTED,
        ]);
        $domain->delete();

        $code = \Artisan::call("domain:status {$domain->namespace}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("Status (1): new (1)", $output);
    }
}
