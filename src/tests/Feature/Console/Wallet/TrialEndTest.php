<?php

namespace Tests\Feature\Console\Wallet;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrialEndTest extends TestCase
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
     * Test command run
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('wallets-controller@kolabnow.com', [
                'status' => User::STATUS_IMAP_READY | User::STATUS_LDAP_READY | User::STATUS_ACTIVE,
        ]);
        $wallet = $user->wallets()->first();

        DB::table('users')->update(['created_at' => \now()->clone()->subMonthsNoOverflow(2)->subHours(1)]);

        // Expect no wallets in after-trial state
        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        // Test an email sent
        $user->created_at = \now()->clone()->subMonthNoOverflow();
        $user->save();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertPushed(\App\Jobs\TrialEndEmail::class, 1);
        Queue::assertPushed(\App\Jobs\TrialEndEmail::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'account');
            return $job_user->id === $user->id;
        });

        $dt = $wallet->getSetting('trial_end_notice');
        $this->assertMatchesRegularExpression('/^' . date('Y-m-d') . ' [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $dt);

        // Test no duplicate email sent for the same wallet
        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        // Test not imap ready user - no email sent
        $wallet->setSetting('trial_end_notice', null);
        $user->status = User::STATUS_NEW | User::STATUS_LDAP_READY | User::STATUS_ACTIVE;
        $user->save();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        // Test deleted user - no email sent
        $user->status = User::STATUS_NEW | User::STATUS_LDAP_READY | User::STATUS_ACTIVE | User::STATUS_IMAP_READY;
        $user->save();
        $user->delete();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        $this->assertNull($wallet->getSetting('trial_end_notice'));
    }
}
