<?php

namespace Tests\Feature\Console\Domain;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatusTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
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
        $user = $this->getTestUser('john@kolab.org');
        $domain = $this->getTestDomain('domain-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $domain->assignPackage($package_domain, $user);
        $domain->delete();

        $code = \Artisan::call("domain:status {$domain->namespace}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("Status (1): deleted (8)", $output);
    }
}
