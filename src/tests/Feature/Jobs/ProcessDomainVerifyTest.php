<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessDomainVerify;
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

        Domain::where('namespace', 'gmail.com')
            ->orWhere('namespace', 'some-non-existing-domain.fff')
            ->delete();
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

        $job = new ProcessDomainVerify($domain);
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

        $job = new ProcessDomainVerify($domain);
        $job->handle();

        $this->assertFalse($domain->fresh()->isVerified());
    }
}
