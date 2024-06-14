<?php

namespace Tests\Feature\Console\Domain;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
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

        // Existing domain
        $ns = \config('app.domain');
        $code = \Artisan::call("domain:create $ns");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain $ns already exists.", $output);

        // Existing domain (with --force param)
        $code = \Artisan::call("domain:create $ns --force");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain $ns not marked as deleted... examine more closely", $output);

        // A new domain
        $code = \Artisan::call("domain:create domain-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $domain = \App\Domain::where('namespace', 'domain-delete.com')->first();

        $this->assertSame("Domain domain-delete.com created with ID {$domain->id}. "
            . "Remember to assign it to a wallet with 'domain:set-wallet'", $output);

        $this->assertTrue($domain->isExternal());
        $this->assertNull($domain->wallet());
        $this->assertSame('domain-delete.com', $domain->namespace);

        $domain->status |= \App\Domain::STATUS_ACTIVE;
        $domain->save();
        $domain->delete();

        $domain->refresh();

        $this->assertTrue($domain->trashed());
        $this->assertTrue($domain->isActive());

        // Deleted domain
        $code = \Artisan::call("domain:create domain-delete.com --force");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("Domain domain-delete.com with ID {$domain->id} revived. "
            . "Remember to assign it to a wallet with 'domain:set-wallet'", $output);

        $domain->refresh();

        $this->assertTrue($domain->isNew());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->trashed());

        $this->deleteTestDomain('domain-delete.com');

        // Test --tenant option
        $tenant = \App\Tenant::orderBy('id', 'desc')->first();
        $code = \Artisan::call("domain:create domain-delete.com --tenant={$tenant->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $domain = \App\Domain::where('namespace', 'domain-delete.com')->first();

        $this->assertTrue($domain->isNew());
        $this->assertSame($tenant->id, $domain->tenant_id);
    }
}
