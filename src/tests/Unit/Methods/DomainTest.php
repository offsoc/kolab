<?php

namespace Tests\Unit\Methods;

use Tests\TestCase;

class DomainTest extends TestCase
{
    private $domain;

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->domain = new \App\Domain();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    /**
     * Test lower-casing namespace attribute.
     */
    public function testSetNamespaceAttributeLowercases()
    {
        $this->domain = new \App\Domain();

        $this->domain->namespace = 'UPPERCASE';

        // @phpstan-ignore-next-line
        $this->assertTrue($this->domain->namespace === 'uppercase');
    }

    /**
     * Test setting the status to something invalid
     */
    public function testSetStatusAttributeInvalid()
    {
        $this->expectException(\Exception::class);

        $this->domain->status = 123456;
    }

    /**
     * Test public domain.
     */
    public function testSetStatusAttributeOnPublicDomain()
    {
        $this->domain->{'type'} = \App\Domain::TYPE_PUBLIC;

        $this->domain->status = 115;

        $this->assertTrue($this->domain->status == 115);
    }

    /**
     * Test status mutations
     */
    public function testSetStatusAttributeActiveMakesForNotNew()
    {
        $this->domain->status = \App\Domain::STATUS_NEW;

        $this->assertTrue($this->domain->isNew());
        $this->assertFalse($this->domain->isActive());

        $this->domain->status |= \App\Domain::STATUS_ACTIVE;

        $this->assertFalse($this->domain->isNew());
        $this->assertTrue($this->domain->isActive());
    }

    /**
     * Verify setting confirmed sets verified.
     */
    public function testSetStatusAttributeConfirmedMakesForVerfied()
    {
        $this->domain->status = \App\Domain::STATUS_CONFIRMED;

        $this->assertTrue($this->domain->isConfirmed());
        $this->assertTrue($this->domain->isVerified());
    }

    /**
     * Verify setting confirmed sets active.
     */
    public function testSetStatusAttributeConfirmedMakesForActive()
    {
        $this->domain->status = \App\Domain::STATUS_CONFIRMED;

        $this->assertTrue($this->domain->isConfirmed());
        $this->assertTrue($this->domain->isActive());
    }

    /**
     * Verify setting deleted drops active.
     */
    public function testSetStatusAttributeDeletedVoidsActive()
    {
        $this->domain->status = \App\Domain::STATUS_ACTIVE;

        $this->assertTrue($this->domain->isActive());
        $this->assertFalse($this->domain->isNew());
        $this->assertFalse($this->domain->isDeleted());

        $this->domain->status |= \App\Domain::STATUS_DELETED;

        $this->assertFalse($this->domain->isActive());
        $this->assertFalse($this->domain->isNew());
        $this->assertTrue($this->domain->isDeleted());
    }

    /**
     * Verify setting suspended drops active.
     */
    public function testSetStatusAttributeSuspendedVoidsActive()
    {
        $this->domain->status = \App\Domain::STATUS_ACTIVE;

        $this->assertTrue($this->domain->isActive());
        $this->assertFalse($this->domain->isSuspended());

        $this->domain->status |= \App\Domain::STATUS_SUSPENDED;

        $this->assertFalse($this->domain->isActive());
        $this->assertTrue($this->domain->isSuspended());
    }

    /**
     * Verify we can suspend a suspended domain without disaster.
     *
     * This doesn't change anything to trigger a save.
     */
    public function testSuspendForSuspendedDomain()
    {
        $this->domain->status = \App\Domain::STATUS_ACTIVE;

        $this->domain->status |= \App\Domain::STATUS_SUSPENDED;

        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());

        $this->domain->suspend();

        $this->assertTrue($this->domain->isSuspended());
        $this->assertFalse($this->domain->isActive());
    }

    /**
     * Verify we can unsuspend an active (unsuspended) domain
     *
     * This doesn't change anything to trigger a save.
     */
    public function testUnsuspendForActiveDomain()
    {
        $this->domain->status = \App\Domain::STATUS_ACTIVE;

        $this->assertFalse($this->domain->isSuspended());
        $this->assertTrue($this->domain->isActive());

        $this->domain->unsuspend();

        $this->assertFalse($this->domain->isSuspended());
        $this->assertTrue($this->domain->isActive());
    }
}
