<?php

namespace Tests\Unit;

use App\Wallet;
use Tests\TestCase;

class WalletTest extends TestCase
{
    /**
     * Test Wallet::money()
     *
     * @return void
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
}
