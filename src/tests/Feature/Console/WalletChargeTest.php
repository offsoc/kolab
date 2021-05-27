<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletChargeTest extends TestCase
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
            ->assertExitCode(1);

        Queue::assertNothingPushed();

        // The wallet has no entitlements, expect no charge and no check
        $this->artisan('wallet:charge ' . $wallet->id)
            ->assertExitCode(0);

        Queue::assertNothingPushed();

        // The wallet has no entitlements, but has negative balance
        $wallet->balance = -100;
        $wallet->save();

        $this->artisan('wallet:charge ' . $wallet->id)
            ->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\WalletCharge::class, 0);
        Queue::assertPushed(\App\Jobs\WalletCheck::class, 1);
        Queue::assertPushed(\App\Jobs\WalletCheck::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        Queue::fake();

        // The wallet has entitlements to charge, and negative balance
        $sku = \App\Sku::where('title', 'mailbox')->first();
        $entitlement = \App\Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => 100,
                'entitleable_id' => $user->id,
                'entitleable_type' => \App\User::class,
        ]);
        \App\Entitlement::where('id', $entitlement->id)->update([
                'created_at' => \Carbon\Carbon::now()->subMonths(1),
                'updated_at' => \Carbon\Carbon::now()->subMonths(1),
        ]);
        \App\User::where('id', $user->id)->update([
                'created_at' => \Carbon\Carbon::now()->subMonths(1),
                'updated_at' => \Carbon\Carbon::now()->subMonths(1),
        ]);

        $this->assertSame(100, $wallet->fresh()->chargeEntitlements(false));

        $this->artisan('wallet:charge ' . $wallet->id)
            ->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\WalletCharge::class, 1);
        Queue::assertPushed(\App\Jobs\WalletCharge::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });

        Queue::assertPushed(\App\Jobs\WalletCheck::class, 1);
        Queue::assertPushed(\App\Jobs\WalletCheck::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });
    }

    /**
     * Test command run for all wallets
     */
    public function testHandleAll(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $wallet->balance = 0;
        $wallet->save();

        // backdate john's entitlements and set balance=0 for all wallets
        $this->backdateEntitlements($user->entitlements, \Carbon\Carbon::now()->subWeeks(5));
        \App\Wallet::where('balance', '<', '0')->update(['balance' => 0]);

        $user2 = $this->getTestUser('wallet-charge@kolabnow.com');
        $wallet2 = $user2->wallets()->first();
        $wallet2->balance = -100;
        $wallet2->save();

        Queue::fake();

        // Non-existing wallet ID
        $this->artisan('wallet:charge')->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\WalletCheck::class, 2);
        Queue::assertPushed(\App\Jobs\WalletCheck::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });
        Queue::assertPushed(\App\Jobs\WalletCheck::class, function ($job) use ($wallet2) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet2->id;
        });

        Queue::assertPushed(\App\Jobs\WalletCharge::class, 1);
        Queue::assertPushed(\App\Jobs\WalletCharge::class, function ($job) use ($wallet) {
            $job_wallet = TestCase::getObjectProperty($job, 'wallet');
            return $job_wallet->id === $wallet->id;
        });
    }
}
