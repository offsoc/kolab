<?php

namespace Tests\Feature\Jobs\Domain;

use App\Domain;
use App\Jobs\Domain\UpdateJob;
use App\Support\Facades\LDAP;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-create-test.com');
    }

    protected function tearDown(): void
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

        LDAP::shouldReceive('updateDomain')->once()->with($domain)->andReturn(true);

        $job = new UpdateJob($domain->id);
        $job->handle();

        // TODO: More cases
        $this->markTestIncomplete();
    }
}
