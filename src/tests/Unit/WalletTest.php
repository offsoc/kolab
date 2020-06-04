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
        $wallet = new Wallet([
                'currency' => 'CHF',
        ]);

        $money = $wallet->money(-123);
        $this->assertSame('-1,23 CHF', $money);

        // This test is here to remind us that the method will give
        // different results for different locales, but also depending
        // if NumberFormatter (intl extension) is installed or not.
        // NumberFormatter also returns some surprising output for
        // some locales and e.g. negative numbers.
        // We'd have to improve on that as soon as we'd want to use
        // other locale than the default de_DE.
    }
}
