<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Domain methods where additional infrastructure is required, such as Redis and
 * MariaDB.
 */
class DomainTest extends TestCase
{
    /**
     * One of the domains that is available for public registration.
     *
     * @var \App\Domain
     */
    private $publicDomain;

    /**
     * A newly generated user in a public domain.
     *
     * @var \App\User
     */
    private $publicDomainUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->publicDomain = \App\Domain::where('type', \App\Domain::TYPE_PUBLIC)->first();
        $this->publicDomainUser = $this->getTestUser('john@' . $this->publicDomain->namespace);
    }

    public function tearDown(): void
    {
        $this->deleteTestUser($this->publicDomainUser->email);

        parent::tearDown();
    }

    /**
     * Test that a public domain can not be assigned a package.
     */
    public function testAssignPackagePublicDomain()
    {
        $domain = \App\Domain::where('type', \App\Domain::TYPE_PUBLIC)->first();
        $package = \App\Package::where('title', 'domain-hosting')->first();
        $sku = \App\Sku::where('title', 'domain-hosting')->first();

        $numEntitlementsBefore = $sku->entitlements->count();

        $domain->assignPackage($package, $this->publicDomainUser);

        // the domain is not associated with any entitlements.
        $entitlement = $domain->entitlement;
        $this->assertNull($entitlement);

        // the sku is not associated with more entitlements than before
        $numEntitlementsAfter = $sku->fresh()->entitlements->count();
        $this->assertEqual($numEntitlementsBefore, $numEntitlementsAfter);
    }

    /**
     * Verify a domain that is assigned to a wallet already, can not be assigned to another wallet.
     */
    public function testAssignPackageDomainWithWallet()
    {
        $package = \App\Package::where('title', 'domain-hosting')->first();
        $sku = \App\Sku::where('title', 'domain-hosting')->first();

        $this->assertSame($this->domainHosted->wallet()->owner->email, $this->domainOwner->email);

        $numEntitlementsBefore = $sku->entitlements->count();

        $this->domainHosted->assignPackage($package, $this->publicDomainUser);

        // the sku is not associated with more entitlements than before
        $numEntitlementsAfter = $sku->fresh()->entitlements->count();
        $this->assertEqual($numEntitlementsBefore, $numEntitlementsAfter);

        // the wallet for this temporary user still holds no entitlements
        $wallet = $this->publicDomainUser->wallets()->first();
        $this->assertCount(0, $wallet->entitlements);
    }

    /**
     * Verify the function getPublicDomains returns a flat, single-dimensional, disassociative array of strings.
     */
    public function testGetPublicDomainsIsFlatArray()
    {
        $domains = \App\Domain::getPublicDomains();

        $this->assertisArray($domains);

        foreach ($domains as $domain) {
            $this->assertIsString($domain);
        }

        foreach ($domains as $num => $domain) {
            $this->assertIsInt($num);
            $this->assertIsString($domain);
        }
    }

    public function testGetPublicDomainsIsSorted()
    {
        $domains = \App\Domain::getPublicDomains();

        sort($domains);

        $this->assertSame($domains, \App\Domain::getPublicDomains());
    }


    /**
     * Verify we can suspend an active domain.
     */
    public function testSuspendForActiveDomain()
    {
        Queue::fake();

        $this->domainHosted->status |= \App\Domain::STATUS_ACTIVE;

        $this->assertFalse($this->domainHosted->isSuspended());
        $this->assertTrue($this->domainHosted->isActive());

        $this->domainHosted->suspend();

        $this->assertTrue($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());
    }

    /**
     * Verify we can unsuspend a suspended domain
     */
    public function testUnsuspendForSuspendedDomain()
    {
        Queue::fake();

        $this->domainHosted->status |= \App\Domain::STATUS_SUSPENDED;

        $this->assertTrue($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());

        $this->domainHosted->unsuspend();

        $this->assertFalse($this->domainHosted->isSuspended());
        $this->assertTrue($this->domainHosted->isActive());
    }

    /**
     * Verify we can unsuspend a suspended domain that wasn't confirmed
     */
    public function testUnsuspendForSuspendedUnconfirmedDomain()
    {
        Queue::fake();

        $this->domainHosted->status = \App\Domain::STATUS_NEW | \App\Domain::STATUS_SUSPENDED;

        $this->assertTrue($this->domainHosted->isNew());
        $this->assertTrue($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());
        $this->assertFalse($this->domainHosted->isConfirmed());
        $this->assertFalse($this->domainHosted->isVerified());

        $this->domainHosted->unsuspend();

        $this->assertTrue($this->domainHosted->isNew());
        $this->assertFalse($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());
        $this->assertFalse($this->domainHosted->isConfirmed());
        $this->assertFalse($this->domainHosted->isVerified());
    }

    /**
     * Verify we can unsuspend a suspended domain that was verified but not confirmed
     */
    public function testUnsuspendForSuspendedVerifiedUnconfirmedDomain()
    {
        Queue::fake();

        $this->domainHosted->status = \App\Domain::STATUS_NEW
            | \App\Domain::STATUS_SUSPENDED
            | \App\Domain::STATUS_VERIFIED;

        $this->assertTrue($this->domainHosted->isNew());
        $this->assertTrue($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());
        $this->assertFalse($this->domainHosted->isConfirmed());
        $this->assertTrue($this->domainHosted->isVerified());

        $this->domainHosted->unsuspend();

        $this->assertTrue($this->domainHosted->isNew());
        $this->assertFalse($this->domainHosted->isSuspended());
        $this->assertFalse($this->domainHosted->isActive());
        $this->assertFalse($this->domainHosted->isConfirmed());
        $this->assertTrue($this->domainHosted->isVerified());
    }
}
