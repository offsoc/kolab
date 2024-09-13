<?php

namespace Tests\Feature\Console\Domain;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SetStatusTest extends TestCase
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
        // Non-existing domain
        $code = \Artisan::call("domain:set-status unknown.org 1");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        $domain = $this->getTestDomain('domain-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);

        Queue::fake();

        $code = \Artisan::call("domain:set-status domain-delete.com " . \App\Domain::STATUS_LDAP_READY);
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('Status (64): ldapReady (64)', $output);
        Queue::assertPushed(\App\Jobs\Domain\UpdateJob::class, 1);

        $domain->refresh();

        $this->assertSame(\App\Domain::STATUS_LDAP_READY, $domain->status);
    }
}
