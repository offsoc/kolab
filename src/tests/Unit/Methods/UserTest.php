<?php

namespace Tests\Unit\Methods;

use Tests\TestCase;

class UserTest extends TestCase
{
    private $statuses = [
        \App\User::STATUS_NEW,
        \App\User::STATUS_ACTIVE,
        \App\User::STATUS_SUSPENDED,
        \App\User::STATUS_DELETED,
        \App\User::STATUS_IMAP_READY,
        \App\User::STATUS_LDAP_READY
    ];

    public function setUp(): void
    {
        $this->user = new \App\User();
    }

    public function tearDown(): void
    {
        // nothing to do here
    }

    public function testGetJWTCustomClaims()
    {
        $this->assertEmpty($this->user->getJWTCustomClaims());
    }

    public function testIsNewFailure()
    {
        $this->assertFalse($this->user->isNew());
    }

    public function testIsNewSuccess()
    {
        $this->user->status |= \App\User::STATUS_NEW;
        $this->assertTrue($this->user->isNew());
    }

    public function testIsActiveFailure()
    {
        $this->assertFalse($this->user->isActive());
    }

    public function testIsActiveSuccess()
    {
        $this->user->status |= \App\User::STATUS_ACTIVE;
        $this->assertTrue($this->user->isActive());
    }

    public function testIsSuspendedFailure()
    {
        $this->assertFalse($this->user->isSuspended());
    }

    public function testIsSuspendedSuccess()
    {
        $this->user->status |= \App\User::STATUS_SUSPENDED;
        $this->assertTrue($this->user->isSuspended());
    }

    public function testIsDeletedFailure()
    {
        $this->assertFalse($this->user->isDeleted());
    }

    public function testIsDeletedSuccess()
    {
        $this->user->status |= \App\User::STATUS_DELETED;
        $this->assertTrue($this->user->isDeleted());
    }

    public function testIsImapReadyFailure()
    {
        $this->assertFalse($this->user->isImapReady());
    }

    public function testIsImapReadySuccess()
    {
        $this->user->status |= \App\User::STATUS_IMAP_READY;
        $this->assertTrue($this->user->isImapReady());
    }

    public function testIsLdapReadyFailure()
    {
        $this->assertFalse($this->user->isLdapReady());
    }

    public function testIsLdapReadySuccess()
    {
        $this->user->status |= \App\User::STATUS_LDAP_READY;
        $this->assertTrue($this->user->isLdapReady());
    }

    public function testSetStatusAttributeAnyValid()
    {
        foreach ($this->statuses as $status) {
            $this->user->status = $status;

            $this->assertSame($this->user->status, $status);
        }
    }

    public function testSetStatusAttributeAnyValidCombination()
    {
        foreach ($this->statuses as $status) {
            $this->user->status |= $status;

            $this->assertTrue(($this->user->status & $status) > 0);
        }
    }

    public function testSetStatusAttributeInvalidTooHigh()
    {
        $this->expectException(\Exception::class);

        $this->user->status = pow(2, 6) + 1;
    }

    public function testSetStatusAttributeNonNumeric()
    {
        $this->expectException(\Exception::class);

        $this->user->status = 'something definitely invalid for an integer field';
    }

    public function testSuspendSuspendedUser()
    {
        $this->user->status |= \App\User::STATUS_SUSPENDED;
        $this->user->suspend();

        $this->assertTrue($this->user->isSuspended());
    }

    public function testUnsuspendNotSuspendedUser()
    {
        $this->user->status |= \App\User::STATUS_ACTIVE;
        $this->user->unsuspend();

        $this->assertFalse($this->user->isSuspended());
    }
}
