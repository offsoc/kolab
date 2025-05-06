<?php

namespace Tests\Feature\Console\Wallet;

use App\Discount;
use App\Package;
use Tests\TestCase;

class SetDiscountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command run for a specified wallet
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $package = Package::where('title', 'kolab')->first();
        $user->assignPackage($package);
        $wallet = $user->wallets()->first();
        $discount = Discount::withObjectTenantContext($user)->where('discount', 100)->first();

        // Invalid wallet id
        $code = \Artisan::call("wallet:set-discount 123 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Invalid discount id
        $code = \Artisan::call("wallet:set-discount {$wallet->id} 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Discount not found.", $output);

        // Assign a discount
        $code = \Artisan::call("wallet:set-discount {$wallet->id} {$discount->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $wallet->refresh();
        $this->assertSame($discount->id, $wallet->discount_id);

        // Remove the discount
        $code = \Artisan::call("wallet:set-discount {$wallet->id} 0");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $wallet->refresh();
        $this->assertNull($wallet->discount_id);
    }
}
