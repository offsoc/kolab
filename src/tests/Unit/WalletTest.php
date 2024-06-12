<?php

namespace Tests\Unit;

use App\Wallet;
use Carbon\Carbon;
use Tests\TestCase;

class WalletTest extends TestCase
{
    /**
     * Test Wallet::money()
     */
    public function testMoney()
    {
        // This test is here to remind us that the method will give
        // different results for different locales

        $wallet = new Wallet(['currency' => 'CHF']);
        $money = $wallet->money(-123);

        $this->assertSame('-1,23 CHF', $money);

        $wallet = new Wallet(['currency' => 'EUR']);
        $money = $wallet->money(-123);

        $this->assertSame('-1,23 €', $money);
    }

    /**
     * Test Wallet::entitlementCosts()
     */
    public function testEntitlementCosts()
    {
        $discount = \App\Discount::where('discount', 30)->first();
        $wallet = new Wallet(['currency' => 'CHF', 'id' => 123]);
        $ent = new \App\Entitlement([
                'wallet_id' => $wallet->id,
                'sku_id' => 456,
                'cost' => 100,
                'fee' => 50,
                'entitleable_id' => 789,
                'entitleable_type' => \App\User::class,
        ]);

        $wallet->discount = $discount; // @phpstan-ignore-line

        // Test calculating with daily price, period spread over two months
        Carbon::setTestNow(Carbon::create(2021, 5, 5, 12));
        $ent->created_at = Carbon::now()->subDays(20);
        $ent->updated_at = Carbon::now()->subDays(20);

        $result = $this->invokeMethod($wallet, 'entitlementCosts', [$ent, null, true]);

        // cost: floor(100 / 30 * 15 * 70%) + floor(100 / 31 * 5 * 70%) = 46
        $this->assertSame(46, $result[0]);
        // fee: floor(50 / 30 * 15) + floor(50 / 31 * 5) = 33
        $this->assertSame(33, $result[1]);
        $this->assertTrue(Carbon::now()->equalTo($result[2])); // end of period

        // Test calculating with daily price, period spread over three months
        Carbon::setTestNow(Carbon::create(2021, 5, 5, 12));
        $ent->created_at = Carbon::create(2021, 3, 25, 12);
        $ent->updated_at = Carbon::create(2021, 3, 25, 12);

        $result = $this->invokeMethod($wallet, 'entitlementCosts', [$ent, null, true]);

        // cost: floor(100 * 70%) + floor(100 / 30 * 5 * 70%) + floor(100 / 31 * 5 * 70%) = 92
        $this->assertSame(92, $result[0]);
        // fee: 50 + floor(50 / 30 * 5) + floor(50 / 31 * 5) = 66
        $this->assertSame(66, $result[1]);
        $this->assertTrue(Carbon::now()->equalTo($result[2])); // end of period

        // TODO: More cases
    }
}
