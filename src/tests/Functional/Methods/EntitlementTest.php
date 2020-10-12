<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Entitlement methods.
 */
class EntitlementTest extends TestCase
{
    private $skuDomainHosting;
    private $skuMailbox;
    private $skuStorage;

    public function setUp(): void
    {
        parent::setUp();

        $this->skuDomainHosting = \App\Sku::where('title', 'domain-hosting')->first();
        $this->skuMailbox = \App\Sku::where('title', 'mailbox')->first();
        $this->skuStorage = \App\Sku::where('title', 'storage')->first();

        $this->discounts = [
            '50' => \App\Discount::create(['discount' => 50, 'active' => 1, 'description' => 'testing 50%']),
            '100' => \App\Discount::create(['discount' => 100, 'active' => 1, 'description' => 'testing 100%'])
        ];
    }

    /**
     * Verify that an entitlement without a wallet associated with it will simply return zero.
     */
    public function testCostsPerDayWithoutWallet()
    {
        $entitlement = new \App\Entitlement();

        $daysInLastMonth = \App\Utils::daysInLastMonth();
        $entitlement->cost = $daysInLastMonth * 100;

        $this->assertEqual($entitlement->costsPerDay(), (float) 0.0);
    }

    /**
     * Verify that an entitlement without cost returns zero.
     */
    public function testCostsPerDayWithoutCost()
    {
        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuStorage->id)->first();

        $this->assertIsInt($entitlement->cost);
        $this->assertEqual($entitlement->cost, 0);

        $costsPerDay = $entitlement->costsPerDay();

        $this->assertIsFloat($costsPerDay);

        $this->assertEqual($costsPerDay, (float) 0.0);
    }

    /**
     * Verify that an entitlement with costs returns something between zero and the original price, and that the
     * original cost divided by the minimum number of days in any month (28) is higher or equal to the costs per day.
     */
    public function testCostsPerDayWithWalletWithoutDiscount()
    {
        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuMailbox->id)->first();

        $this->assertIsInt($entitlement->cost);
        $this->assertEqual($entitlement->cost, $this->skuMailbox->cost);

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $costsPerDay = $entitlement->costsPerDay();

        $this->assertIsFloat($costsPerDay);

        $this->assertTrue($costsPerDay > 0);
        $this->assertTrue($costsPerDay < 444);
        $this->assertTrue($costsPerDay <= (444 / 28));
    }

    /**
     * Verify that an entitlement with costs returns something between zero and 50% of the original price, and that the
     * original cost divided by the minimum number of days in any month (28) is higher or equal to the costs per day.
     */
    public function testCostsPerDayWithWalletWithDiscountHalf()
    {
        $wallet = $this->domainOwner->wallets()->first();

        $wallet->discount_id = $this->discounts['50']->id;
        $wallet->save();

        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuMailbox->id)->first();

        $this->assertIsInt($entitlement->cost);
        $this->assertEqual($entitlement->cost, $this->skuMailbox->cost);

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $costsPerDay = $entitlement->costsPerDay();

        $this->assertIsFloat($costsPerDay);

        $this->assertTrue($costsPerDay > 0);
        $this->assertTrue($costsPerDay < 222);
        $this->assertTrue($costsPerDay <= (222 / 28));
    }

    public function testCreateTransaction()
    {
        $this->markTestIncomplete();
    }

    public function testEntitleableReturnDomain()
    {
        $entitlements = $this->domainOwner->wallets()->first()->entitlements();

        $entitlement = $entitlements->where('sku_id', $this->skuDomainHosting->id)->first();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Relations\MorphTo', $entitlement->entitleable());
        $this->assertInstanceOf('App\Domain', $entitlement->entitleable);
    }

    public function testEntitleableReturnUser()
    {
        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuMailbox->id)->first();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Relations\MorphTo', $entitlement->entitleable());
        $this->assertInstanceOf('App\User', $entitlement->entitleable);
    }

    public function testEntitleableDomain()
    {
        $entitlements = $this->domainOwner->wallets()->first()->entitlements();

        $entitlement = $entitlements->where('sku_id', $this->skuDomainHosting->id)->first();

        $domain = $entitlement->entitleable;

        $this->assertEqual($domain->id, $this->domainHosted->id);
    }

    public function testEntitleableUser()
    {
        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuMailbox->id)->first();

        $user = $entitlement->entitleable;

        $this->assertEqual($user->id, $this->domainOwner->id);
    }

    public function testEntitleableTitleDomain()
    {
        $entitlements = $this->domainOwner->wallets()->first()->entitlements();

        $entitlement = $entitlements->where('sku_id', $this->skuDomainHosting->id)->first();

        $namespace = $entitlement->entitleableTitle();

        $this->assertSame($namespace, $this->domainHosted->namespace);
    }

    public function testEntitleableTitleInvalidEntitleableId()
    {
        $this->expectException(\Exception::class);

        $wallet = $this->domainOwner->wallets()->first();

        $entitlement = \App\Entitlement::create(
            [
                'sku_id' => $this->skuMailbox->id,
                'wallet_id' => $wallet->id,
                'entitleable_id' => 1234,
                'entitleable_type' => \App\User::class,
                'cost' => 0
            ]
        );

        $title = $entitlement->entitleableTitle();

        $this->assertNull($title);
    }

    public function testEntitleableTitleInvalidEntitleableTruncation()
    {
        // without the observer change, an exception would be thrown.
        //$this->expectException(\Exception::class);

        $wallet = $this->domainOwner->wallets()->first();

        $entitlement = \App\Entitlement::create(
            [
                'sku_id' => $this->skuMailbox->id,
                'wallet_id' => $wallet->id,
                'entitleable_id' => $wallet->id,
                'entitleable_type' => \App\Wallet::class,
                'cost' => 0
            ]
        );

        $title = $entitlement->entitleableTitle();

        $this->assertNull($title);
    }

    public function testEntitleableTitleInvalidEntitleableOverloadedProperty()
    {
        // without the observer change, an exception would be thrown.
        //$this->expectException(\Exception::class);

        $wallet = $this->domainOwner->wallets()->first();

        $entitlement = \App\Entitlement::create(
            [
                'sku_id' => $this->skuMailbox->id,
                'wallet_id' => $wallet->id,
                'entitleable_id' => 1234,
                'entitleable_type' => \App\Wallet::class,
                'cost' => 0
            ]
        );

        $title = $entitlement->entitleableTitle();

        $this->assertNull($title);
    }

    public function testEntitleableTitleUser()
    {
        $entitlement = $this->domainOwner->entitlements()->where('sku_id', $this->skuMailbox->id)->first();

        $email = $entitlement->entitleableTitle();

        $this->assertSame($email, $this->domainOwner->email);
    }

    public function testSku()
    {
        $entitlements = $this->domainOwner->wallets()->first()->entitlements();

        $entitlements->each(
            function ($entitlement) {
                $this->assertInstanceOf('Illuminate\Database\Eloquent\Relations\BelongsTo', $entitlement->sku());
                $this->assertInstanceOf('App\Sku', $entitlement->sku);
            }
        );
    }

    public function testWallet()
    {
        $this->markTestIncomplete();
    }
}
