<?php

namespace Tests\Feature\Jobs\Domain;

use App\Domain;
use App\Support\Facades\LDAP;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-create-test.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestDomain('domain-create-test.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $domain = $this->getTestDomain(
            'domain-create-test.com',
            [
                'status' => Domain::STATUS_NEW | Domain::STATUS_LDAP_READY,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        \config(['app.with_ldap' => true]);

        $this->assertTrue($domain->isLdapReady());
        $this->assertFalse($domain->isDeleted());

        LDAP::shouldReceive('deleteDomain')->once()->with($domain)->andReturn(true);

        $job = new \App\Jobs\Domain\DeleteJob($domain->id);
        $job->handle();

        $domain->refresh();
        $this->assertFalse($domain->isLdapReady());
        $this->assertTrue($domain->isDeleted());

        // TODO: More cases
        $this->markTestIncomplete();
    }
}
