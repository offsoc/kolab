<?php

namespace Tests\Feature\Console\Wallet;

use App\Discount;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GetDiscountTest extends TestCase
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
        Queue::fake();

        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Non-existing wallet
        $code = \Artisan::call("wallet:get-discount 123");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // No discount
        $code = \Artisan::call("wallet:get-discount {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("No discount on this wallet.", $output);

        $code = \Artisan::call("wallet:get-discount {$wallet->id} --int");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("0", $output);

        $discount = Discount::withObjectTenantContext($user)->where('discount', 10)->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        // With discount
        $code = \Artisan::call("wallet:get-discount {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("10% [TEST] Test voucher", $output);

        $code = \Artisan::call("wallet:get-discount {$wallet->id} --int");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("10", $output);
    }
}
