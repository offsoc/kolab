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

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');

        parent::tearDown();
    }

    /**
     * Tests for User::AddEntitlement()
     */
    public function testUserAddEntitlement(): void
    {
        $package_domain = Package::where('title', 'domain-hosting')->first();
        $package_kolab = Package::where('title', 'kolab')->first();

        $sku_domain = Sku::where('title', 'domain-hosting')->first();
        $sku_mailbox = Sku::where('title', 'mailbox')->first();

        $owner = $this->getTestUser('entitlement-test@kolabnow.com');
        $user = $this->getTestUser('entitled-user@custom-domain.com');

        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $domain->assignPackage($package_domain, $owner);

        $owner->assignPackage($package_kolab);
        $owner->assignPackage($package_kolab, $user);

        $wallet = $owner->wallets->first();

        $this->assertCount(4, $owner->entitlements()->get());
        $this->assertCount(1, $sku_domain->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(2, $sku_mailbox->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(9, $wallet->entitlements);

        $this->backdateEntitlements($owner->entitlements, Carbon::now()->subMonths(1));

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->fresh()->balance < 0);
    }

    public function testAddExistingEntitlement(): void
    {
        $this->markTestIncomplete();
    }

    public function testEntitlementFunctions(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');

        $package = \App\Package::where('title', 'kolab')->first();

        $user->assignPackage($package);

        $wallet = $user->wallets()->first();
        $this->assertNotNull($wallet);

        $sku = \App\Sku::where('title', 'mailbox')->first();
        $this->assertNotNull($sku);

        $entitlement = Entitlement::where('wallet_id', $wallet->id)->where('sku_id', $sku->id)->first();
        $this->assertNotNull($entitlement);

        $e_sku = $entitlement->sku;
        $this->assertSame($sku->id, $e_sku->id);

        $e_wallet = $entitlement->wallet;
        $this->assertSame($wallet->id, $e_wallet->id);

        $e_entitleable = $entitlement->entitleable;
        $this->assertEquals($user->id, $e_entitleable->id);
        $this->assertTrue($e_entitleable instanceof \App\User);
    }
}
