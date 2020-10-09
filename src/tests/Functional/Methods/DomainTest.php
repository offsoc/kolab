<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->domain = $this->getTestDomain(
            'test.domain',
            [
                'status' => \App\Domain::STATUS_CONFIRMED | \App\Domain::STATUS_VERIFIED,
                'type' => \App\Domain::TYPE_EXTERNAL
            ]
        );
    }

    public function tearDown(): void
    {
        $this->deleteTestDomain('test.domain');

        parent::tearDown();
    }

    /**
     * Verify we can suspend an active domain.
     */
    public function testSuspendForActiveDomain()
    {
        Queue::fake();

        $this->domain->status |= \App\Domain::STATUS_ACTIVE;

        $this->assertFalse($this->domain->isSuspended());
        $this->assertTrue($this->domain->isActive());

        $this->domain->suspend();

        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
    }

    /**
     * Verify we can unsuspend a suspended domain
     */
    public function testUnsuspendForSuspendedDomain()
    {
        Queue::fake();

        $this->domain->status |= \App\Domain::STATUS_SUSPENDED;

        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());

        $this->domain->unsuspend();

        $this->assertFalse($this->domain->isSuspended());
        $this->assertTrue($this->domain->isActive());
    }

    /**
     * Verify we can unsuspend a suspended domain that wasn't confirmed
     */
    public function testUnsuspendForSuspendedUnconfirmedDomain()
    {
        Queue::fake();

        $this->domain->status = \App\Domain::STATUS_NEW | \App\Domain::STATUS_SUSPENDED;

        $this->assertTrue($this->domain->isNew());
        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
        $this->assertFalse($this->domain->isConfirmed());
        $this->assertFalse($this->domain->isVerified());

        $this->domain->unsuspend();

        $this->assertTrue($this->domain->isNew());
        $this->assertFalse($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
        $this->assertFalse($this->domain->isConfirmed());
        $this->assertFalse($this->domain->isVerified());
    }

    /**
     * Verify we can unsuspend a suspended domain that was verified but not confirmed
     */
    public function testUnsuspendForSuspendedVerifiedUnconfirmedDomain()
    {
        Queue::fake();

        $this->domain->status = \App\Domain::STATUS_NEW | \App\Domain::STATUS_SUSPENDED | \App\Domain::STATUS_VERIFIED;

        $this->assertTrue($this->domain->isNew());
        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
        $this->assertFalse($this->domain->isConfirmed());
        $this->assertTrue($this->domain->isVerified());

        $this->domain->unsuspend();

        $this->assertTrue($this->domain->isNew());
        $this->assertFalse($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
        $this->assertFalse($this->domain->isConfirmed());
        $this->assertTrue($this->domain->isVerified());
    }

}
