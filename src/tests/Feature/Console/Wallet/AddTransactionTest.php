<?php

namespace Tests\Feature\Console\Wallet;

use Tests\TestCase;

class AddTransactionTest extends TestCase
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
        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Invalid wallet id
        $code = \Artisan::call("wallet:add-transaction 123 100");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Add credit
        $code = \Artisan::call("wallet:add-transaction {$wallet->id} 100");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $wallet->refresh();
        $this->assertSame(100, $wallet->balance);

        // Add debit with a transaction description
        // Note: The double-dash trick to make it working with a negative number input
        $code = \Artisan::call("wallet:add-transaction --message=debit -- {$wallet->id} -100");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $wallet->refresh();
        $this->assertSame(0, $wallet->balance);
        $this->assertCount(1, $wallet->transactions()->where('description', 'debit')->get());
    }
}
