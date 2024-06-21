<?php

namespace Tests\Feature\Console\Wallet;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BalancesTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');

        \App\Wallet::query()->update(['balance' => 0]);
        \App\Transaction::truncate();
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

        // Expect no wallets with balance=0 when using --negative
        $code = \Artisan::call("wallet:balances --negative");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);

        $wallet->balance = -100;
        $wallet->save();

        // Expect the wallet with a negative balance in output
        $code = \Artisan::call("wallet:balances --negative");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("{$wallet->id}:     -100 ({$user->email})", $output);

        // Test --skip-zeros
        $code = \Artisan::call("wallet:balances --skip-zeros");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$wallet->id}:     -100 ({$user->email})", $output);

        // Test --invalid
        $code = \Artisan::call("wallet:balances --invalid");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$wallet->id}:     -100        0 ({$user->email})", $output);

        $user->delete();

        // Expect no wallet with deleted owner in output
        $code = \Artisan::call("wallet:balances");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, $wallet->id) === false);
    }
}
