<?php

namespace Tests\Feature\Console\Wallet;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpectedTest extends TestCase
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

        // Non-existing user
        $code = \Artisan::call("wallet:expected --user=123");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Expected charges for a specified user
        $code = \Artisan::call("wallet:expected --user={$user->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression(
            "|expect charging wallet {$wallet->id} for user {$user->email} with 0|",
            $output
        );

        // Test --non-zero argument
        $code = \Artisan::call("wallet:expected --user={$user->id} --non-zero");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, $wallet->id) === false);

        // Expected charges for all wallets
        $code = \Artisan::call("wallet:expected");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression(
            "|expect charging wallet {$wallet->id} for user {$user->email} with 0|",
            $output
        );
    }
}
