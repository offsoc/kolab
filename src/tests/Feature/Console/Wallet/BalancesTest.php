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

        // Expect no wallets with balance=0
        $code = \Artisan::call("wallet:balances");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, $wallet->id) === false);

        $wallet->balance = -100;
        $wallet->save();

        // Expect the wallet with a negative balance in output
        $code = \Artisan::call("wallet:balances");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression(
            '|' . preg_quote($wallet->id, '|') . ': {5}-100 \(account: https://.*/admin/accounts/show/'
                . $user->id . ' \(' . preg_quote($user->email, '|') . '\)\)|',
            $output
        );

        $user->delete();

        // Expect no wallet with deleted owner in output
        $code = \Artisan::call("wallet:balances");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(strpos($output, $wallet->id) === false);
    }
}
