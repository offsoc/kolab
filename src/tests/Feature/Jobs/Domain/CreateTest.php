<?php

namespace Tests\Feature\Jobs\Domain;

use App\Domain;
use App\Support\Facades\LDAP;
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
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        \config(['app.with_ldap' => true]);

        $this->assertFalse($domain->isLdapReady());

        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        LDAP::shouldReceive('createDomain')->once()->with($domain)->andReturn(true);

        $job = new \App\Jobs\Domain\CreateJob($domain->id);
        $job->handle();

        $this->assertTrue($domain->fresh()->isLdapReady());

        Queue::assertPushed(\App\Jobs\Domain\VerifyJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Domain\VerifyJob::class,
            function ($job) use ($domain) {
                $domainId = TestCase::getObjectProperty($job, 'domainId');
                $domainNamespace = TestCase::getObjectProperty($job, 'domainNamespace');

                return $domainId === $domain->id &&
                    $domainNamespace === $domain->namespace;
            }
        );

        // Test job releasing on unknown identifier
        $job = (new \App\Jobs\Domain\CreateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased(delay: 5);

        // TODO: the job can't be released infinitely (?), test that
    }
}
