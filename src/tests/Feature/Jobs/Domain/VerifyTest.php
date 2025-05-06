<?php

namespace Tests\Feature\Jobs\Domain;

use App\Domain;
use App\Jobs\Domain\VerifyJob;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('gmail.com');
        $this->deleteTestDomain('some-non-existing-domain.fff');
    }

    protected function tearDown(): void
    {
        $this->deleteTestDomain('gmail.com');
        $this->deleteTestDomain('some-non-existing-domain.fff');

        parent::tearDown();
    }

    /**
     * Test job handle (existing domain)
     *
     * @group dns
     */
    public function testHandle(): void
    {
        $domain = $this->getTestDomain(
            'gmail.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $this->assertFalse($domain->isVerified());

        $job = new VerifyJob($domain->id);
        $job->handle();

        $this->assertTrue($domain->fresh()->isVerified());

        // Test non-existing domain ID
        $job = (new VerifyJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Domain 123 could not be found in the database.");
    }

    /**
     * Test job handle (non-existing domain)
     *
     * @group dns
     */
    public function testHandleNonExisting(): void
    {
        $domain = $this->getTestDomain(
            'some-non-existing-domain.fff',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $this->assertFalse($domain->isVerified());

        $job = new VerifyJob($domain->id);
        $job->handle();

        $this->assertFalse($domain->fresh()->isVerified());
    }
}
