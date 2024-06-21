<?php

namespace Tests\Feature\Console\Wallet;

use Tests\TestCase;

class TransactionsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('test-user1@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('test-user1@kolabnow.com');

        parent::tearDown();
    }

    public function testHandle(): void
    {
        $user = $this->getTestUser('test-user1@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Non-existing wallet
        $code = \Artisan::call("wallet:transactions 123");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Empty wallet
        $code = \Artisan::call("wallet:transactions {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        // Non-Empty wallet
        $this->createTestTransactions($wallet);
        $code = \Artisan::call("wallet:transactions {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertCount(12, explode("\n", $output));

        // With --detail and --balance
        $code = \Artisan::call("wallet:transactions --detail --balance {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertCount(12, explode("\n", $output));
        $this->assertStringContainsString('(balance: 20,00 CHF)', $output);

        // TODO: Add and test some detail sub-transactions
    }
}
