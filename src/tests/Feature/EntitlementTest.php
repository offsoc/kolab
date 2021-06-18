<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Package;
use App\Sku;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');

        parent::tearDown();
    }

    /**
     * Test for Entitlement::costsPerDay()
     */
    public function testCostsPerDay(): void
    {
        // 444
        // 28 days: 15.86
        // 31 days: 14.32
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $package = Package::where('title', 'kolab')->first();
        $mailbox = Sku::where('title', 'mailbox')->first();

        $user->assignPackage($package);

        $entitlement = $user->entitlements->where('sku_id', $mailbox->id)->first();

        $costsPerDay = $entitlement->costsPerDay();

        $this->assertTrue($costsPerDay < 15.86);
        $this->assertTrue($costsPerDay > 14.32);
    }

    /**
     * Tests for entitlements
     * @todo This really should be in User or Wallet tests file
     */
    public function testEntitlements(): void
    {
        $packageDomain = Package::where('title', 'domain-hosting')->first();
        $packageKolab = Package::where('title', 'kolab')->first();

        $skuDomain = Sku::where('title', 'domain-hosting')->first();
        $skuMailbox = Sku::where('title', 'mailbox')->first();

        $owner = $this->getTestUser('entitlement-test@kolabnow.com');
        $user = $this->getTestUser('entitled-user@custom-domain.com');

        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $domain->assignPackage($packageDomain, $owner);
        $owner->assignPackage($packageKolab);
        $owner->assignPackage($packageKolab, $user);

        $wallet = $owner->wallets->first();

        $this->assertCount(4, $owner->entitlements()->get());
        $this->assertCount(1, $skuDomain->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(2, $skuMailbox->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(9, $wallet->entitlements);

        $this->backdateEntitlements(
            $owner->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)
        );

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->fresh()->balance < 0);
    }

    /**
     * @todo This really should be in User tests file
     */
    public function testEntitlementFunctions(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');

        $package = \App\Package::where('title', 'kolab')->first();

        $user->assignPackage($package);

        $wallet = $user->wallets()->first();
        $this->assertNotNull($wallet);

        $sku = \App\Sku::where('title', 'mailbox')->first();

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku->id)->first();

        $this->assertNotNull($entitlement);
        $this->assertSame($sku->id, $entitlement->sku->id);
        $this->assertSame($wallet->id, $entitlement->wallet->id);
        $this->assertEquals($user->id, $entitlement->entitleable->id);
        $this->assertTrue($entitlement->entitleable instanceof \App\User);
    }
}
