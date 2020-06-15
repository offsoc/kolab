<?php

namespace Tests\Feature;

use App\Auth\SecondFactor;
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
     * Tests for User::AddEntitlement()
     */
    public function testUserAddEntitlement(): void
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

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku->id)->first();

        $this->assertNotNull($entitlement);

        $eSKU = $entitlement->sku;
        $this->assertSame($sku->id, $eSKU->id);

        $eWallet = $entitlement->wallet;
        $this->assertSame($wallet->id, $eWallet->id);

        $eEntitleable = $entitlement->entitleable;
        $this->assertEquals($user->id, $eEntitleable->id);
        $this->assertTrue($eEntitleable instanceof \App\User);
    }

    public function testBillDeletedEntitlement(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $package = \App\Package::where('title', 'kolab')->first();

        $storage = \App\Sku::where('title', 'storage')->first();

        $user->assignPackage($package);
        // some additional SKUs so we have something to delete.
        $user->assignSku($storage, 4);

        // the mailbox, the groupware, the 2 original storage and the additional 4
        $this->assertCount(8, $user->fresh()->entitlements);

        $wallet = $user->wallets()->first();

        $this->backdateEntitlements($user->entitlements, Carbon::now()->subWeeks(7));

        $charge = $wallet->chargeEntitlements();

        $this->assertTrue($wallet->balance < 0);

        $balance = $wallet->balance;

        $user->removeSku($storage, 4);

        // we expect the wallet to have been charged.
        $this->assertTrue($wallet->fresh()->balance < $balance);

        $transactions = \App\Transaction::where('object_id', $wallet->id)
            ->where('object_type', \App\Wallet::class)->get();

        // one round of the monthly invoicing, four sku deletions getting invoiced
        $this->assertCount(5, $transactions);
    }
}
