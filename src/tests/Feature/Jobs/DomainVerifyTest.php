<?php

namespace Tests\Feature\Jobs;

use App\Domain;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DomainVerifyTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('gmail.com');
        $this->deleteTestDomain('some-non-existing-domain.fff');
    }

    public function tearDown(): void
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

        $job = new \App\Jobs\Domain\VerifyJob($domain->id);
        $job->handle();

        $this->assertTrue($domain->fresh()->isVerified());
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

        $job = new \App\Jobs\Domain\VerifyJob($domain->id);
        $job->handle();

        $this->assertFalse($domain->fresh()->isVerified());
    }
}
