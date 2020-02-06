<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessDomainCreate;
use App\Domain;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DomainCreateTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Domain::where('namespace', 'domain-create-test.com')->delete();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $domain = $this->getTestDomain(
            'domain-create-test.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $this->assertFalse($domain->isLdapReady());

        $mock = \Mockery::mock('alias:App\Backends\LDAP');
        $mock->shouldReceive('createDomain')
            ->once()
            ->with($domain)
            ->andReturn(null);

        $job = new ProcessDomainCreate($domain);
        $job->handle();

        $this->assertTrue($domain->fresh()->isLdapReady());
    }
}
