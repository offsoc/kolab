<?php

namespace Tests\Feature\Console\Wallet;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallet-charge@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('wallet-charge@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command run for a specified wallet
     */
    public function testHandleSingle(): void
    {
        $user = $this->getTestUser('wallet-charge@kolabnow.com');
        $wallet = $user->wallets()->first();
        $wallet->balance = 0;
        $wallet->save();

        Queue::fake();

        // Non-existing wallet ID
        $this->artisan('wallet:charge 123')
            ->assertExitCode(1)
            ->expectsOutput("Wallet not found.");

        Queue::assertNothingPushed();

        // The wallet has no entitlements, expect no charge and no check
        $this->artisan('wallet:charge ' . $wallet->id)
            ->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\Wallet\CheckJob::class, 1);
        Queue::assertPushed(\App\Jobs\Wallet\CheckJob::class, function ($job) use ($wallet) {
            $job_wallet_id = TestCase::getObjectProperty($job, 'walletId');
            return $job_wallet_id === $wallet->id;
        });
    }

    /**
     * Test command run for all wallets
     */
    public function testHandleAll(): void
    {
        $user1 = $this->getTestUser('john@kolab.org');
        $wallet1 = $user1->wallets()->first();

        $user2 = $this->getTestUser('wallet-charge@kolabnow.com');
        $wallet2 = $user2->wallets()->first();

        $count = \App\Wallet::join('users', 'users.id', '=', 'wallets.user_id')
            ->whereNull('users.deleted_at')
            ->count();

        Queue::fake();

        $this->artisan('wallet:charge')->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\Wallet\CheckJob::class, $count);
        Queue::assertPushed(\App\Jobs\Wallet\CheckJob::class, function ($job) use ($wallet1) {
            $job_wallet_id = TestCase::getObjectProperty($job, 'walletId');
            return $job_wallet_id === $wallet1->id;
        });
        Queue::assertPushed(\App\Jobs\Wallet\CheckJob::class, function ($job) use ($wallet2) {
            $job_wallet_id = TestCase::getObjectProperty($job, 'walletId');
            return $job_wallet_id === $wallet2->id;
        });
    }
}
