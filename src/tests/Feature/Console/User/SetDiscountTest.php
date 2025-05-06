<?php

namespace Tests\Feature\Console\User;

use App\Discount;
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
     * Test command runs
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $wallet = $user->wallets()->first();
        $discount = Discount::withObjectTenantContext($user)->where('discount', 100)->first();

        // Invalid user id
        $code = \Artisan::call("user:set-discount 123 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Invalid discount id
        $code = \Artisan::call("user:set-discount {$user->id} 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Discount not found.", $output);

        // Assign a discount
        $code = \Artisan::call("user:set-discount {$user->id} {$discount->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertSame($discount->id, $wallet->fresh()->discount_id);

        // Remove the discount
        $code = \Artisan::call("user:set-discount {$user->id} 0");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertNull($wallet->fresh()->discount_id);
    }
}
