<?php

namespace Tests\Feature\Console\Wallet;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SetBalanceTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
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
        $code = \Artisan::call("wallet:set-balance 123 123");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Existing wallet
        // Note: The double-dash trick to make it working with a negative number input
        $code = \Artisan::call("wallet:set-balance -- {$wallet->id} -123");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertSame(-123, $wallet->fresh()->balance);
    }
}
