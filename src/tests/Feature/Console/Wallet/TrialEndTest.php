<?php

namespace Tests\Feature\Console\Wallet;

use App\Jobs\Mail\TrialEndJob;
use App\Package;
use App\Plan;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrialEndTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('test-user1@kolabnow.com');
        $this->deleteTestUser('test-user2@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('test-user1@kolabnow.com');
        $this->deleteTestUser('test-user2@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command run
     */
    public function testHandle(): void
    {
        Queue::fake();

        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $user = $this->getTestUser('test-user1@kolabnow.com', [
            'status' => User::STATUS_IMAP_READY | User::STATUS_LDAP_READY | User::STATUS_ACTIVE,
        ]);
        $wallet = $user->wallets()->first();
        $user->assignPlan($plan);

        DB::table('users')->update(['created_at' => \now()->clone()->subMonthsNoOverflow(2)->subHours(1)]);

        // No wallets in after-trial state, no email sent
        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        // Expect no email sent (out of time boundaries)
        $user->created_at = \now()->clone()->subMonthsNoOverflow(1)->addHour();
        $user->save();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();

        // Test an email sent
        $user->created_at = \now()->clone()->subMonthsNoOverflow(1);
        $user->save();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertPushed(TrialEndJob::class, 1);
        Queue::assertPushed(TrialEndJob::class, static function ($job) use ($user) {
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

        // Make sure the non-controller users are omitted
        $user2 = $this->getTestUser('test-user2@kolabnow.com', [
            'status' => User::STATUS_IMAP_READY | User::STATUS_LDAP_READY | User::STATUS_ACTIVE,
        ]);
        $package = Package::withEnvTenantContext()->where('title', 'lite')->first();
        $user->assignPackage($package, $user2);
        $user2->created_at = \now()->clone()->subMonthsNoOverflow(1);
        $user2->save();

        Queue::fake();
        $code = \Artisan::call("wallet:trial-end");
        Queue::assertNothingPushed();
    }
}
