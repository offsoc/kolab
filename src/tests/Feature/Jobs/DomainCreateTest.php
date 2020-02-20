<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DomainCreate;
use App\Domain;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainCreateTest extends TestCase
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
        Queue::fake();

        $domain = $this->getTestDomain(
            'domain-create-test.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $this->assertFalse($domain->isLdapReady());

        $job = new DomainCreate($domain);
        $job->handle();

        $this->assertTrue($domain->fresh()->isLdapReady());
    }
}
