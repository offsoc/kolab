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

    /**
     * @todo This really should be in User or Wallet tests file
     */
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

        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements, $backdate);

        $charge = $wallet->chargeEntitlements();

        $this->assertSame(-1099, $wallet->balance);

        $balance = $wallet->balance;
        $discount = \App\Discount::where('discount', 30)->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        $user->removeSku($storage, 4);

        // we expect the wallet to have been charged for ~3 weeks of use of
        // 4 deleted storage entitlements, it should also take discount into account
        $backdate->addMonthsWithoutOverflow(1);
        $diffInDays = $backdate->diffInDays(Carbon::now());

        // entitlements-num * cost * discount * days-in-month
        $max = intval(4 * 25 * 0.7 * $diffInDays / 28);
        $min = intval(4 * 25 * 0.7 * $diffInDays / 31);

        $wallet->refresh();
        $this->assertTrue($wallet->balance >= $balance - $max);
        $this->assertTrue($wallet->balance <= $balance - $min);

        $transactions = \App\Transaction::where('object_id', $wallet->id)
            ->where('object_type', \App\Wallet::class)->get();

        // one round of the monthly invoicing, four sku deletions getting invoiced
        $this->assertCount(5, $transactions);
    }
}
