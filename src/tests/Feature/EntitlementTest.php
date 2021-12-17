<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Package;
use App\Sku;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
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
        $this->deleteTestGroup('test-group@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestGroup('test-group@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');

        parent::tearDown();
    }

    /**
     * Test for Entitlement::costsPerDay()
     */
    public function testCostsPerDay(): void
    {
        // 500
        // 28 days: 17.86
        // 31 days: 16.129
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $package = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $mailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        $user->assignPackage($package);

        $entitlement = $user->entitlements->where('sku_id', $mailbox->id)->first();

        $costsPerDay = $entitlement->costsPerDay();

        $this->assertTrue($costsPerDay < 17.86);
        $this->assertTrue($costsPerDay > 16.12);
    }

    /**
     * Tests for entitlements
     * @todo This really should be in User or Wallet tests file
     */
    public function testEntitlements(): void
    {
        $packageDomain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $packageKolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();

        $skuDomain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $skuMailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

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

        $this->assertCount(7, $owner->entitlements()->get());
        $this->assertCount(1, $skuDomain->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(2, $skuMailbox->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(15, $wallet->entitlements);

        $this->backdateEntitlements(
            $owner->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)
        );

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->fresh()->balance < 0);
    }

    /**
     * @todo This really should be in User or Wallet tests file
     */
    public function testBillDeletedEntitlement(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $package = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $storage = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $user->assignPackage($package);
        // some additional SKUs so we have something to delete.
        $user->assignSku($storage, 4);

        // the mailbox, the groupware, the 5 original storage and the additional 4
        $this->assertCount(11, $user->fresh()->entitlements);

        $wallet = $user->wallets()->first();

        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements, $backdate);

        $charge = $wallet->chargeEntitlements();

        $this->assertSame(-1090, $wallet->balance);

        $balance = $wallet->balance;
        $discount = \App\Discount::withEnvTenantContext()->where('discount', 30)->first();
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

        // Test that deleting an entitlement on a degraded account costs nothing
        $balance = $wallet->balance;
        User::where('id', $user->id)->update(['status' => $user->status | User::STATUS_DEGRADED]);

        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements()->get(), $backdate);

        $groupware = \App\Sku::withEnvTenantContext()->where('title', 'groupware')->first();
        $entitlement = $wallet->entitlements()->where('sku_id', $groupware->id)->first();
        $entitlement->delete();

        $this->assertSame($wallet->refresh()->balance, $balance);
    }

    /**
     * Test Entitlement::entitlementTitle()
     */
    public function testEntitleableTitle(): void
    {
        Queue::fake();

        $packageDomain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $packageKolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $user = $this->getTestUser('entitled-user@custom-domain.com');
        $group = $this->getTestGroup('test-group@custom-domain.com');

        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $wallet = $user->wallets->first();
        $domain->assignPackage($packageDomain, $user);
        $user->assignPackage($packageKolab);
        $group->assignToWallet($wallet);

        $sku_mailbox = \App\Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $sku_group = \App\Sku::withEnvTenantContext()->where('title', 'group')->first();
        $sku_domain = \App\Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_mailbox->id)->first();

        $this->assertSame($user->email, $entitlement->entitleableTitle());

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_group->id)->first();

        $this->assertSame($group->email, $entitlement->entitleableTitle());

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_domain->id)->first();

        $this->assertSame($domain->namespace, $entitlement->entitleableTitle());

        // Make sure it still works if the entitleable is deleted
        $domain->delete();

        $entitlement->refresh();

        $this->assertSame($domain->namespace, $entitlement->entitleableTitle());
        $this->assertNotNull($entitlement->entitleable);
    }
}
