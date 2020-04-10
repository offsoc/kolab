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
     *
     * @group ldap
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

        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        $job = new DomainCreate($domain);
        $job->handle();

        $this->assertTrue($domain->fresh()->isLdapReady());

        Queue::assertPushed(\App\Jobs\DomainVerify::class, 1);

        Queue::assertPushed(
            \App\Jobs\DomainVerify::class,
            function ($job) use ($domain) {
                $job_domain = TestCase::getObjectProperty($job, 'domain');

                return $job_domain->id === $domain->id &&
                    $job_domain->namespace === $domain->namespace;
            }
        );
    }
}
