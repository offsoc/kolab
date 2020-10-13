<?php

namespace Tests\Feature\Jobs;

use App\Jobs\WalletCheck;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WalletCheckTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $ned = $this->getTestUser('ned@kolab.org');
        if ($ned->isSuspended()) {
            $ned->status -= User::STATUS_SUSPENDED;
            $ned->save();
        }

        $this->deleteTestUser('wallet-check@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $ned = $this->getTestUser('ned@kolab.org');
        if ($ned->isSuspended()) {
            $ned->status -= User::STATUS_SUSPENDED;
            $ned->save();
        }

        $this->deleteTestUser('wallet-check@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test job handle, initial negative-balance notification
     */
    public function testHandleInitial(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $user->setSetting('external_email', 'external@test.com');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance is not negative, double-update+save for proper resetting of the state
        $wallet->balance = -100;
        $wallet->save();
        $wallet->balance = 0;
        $wallet->save();

        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();

        // Balance is negative now
        $wallet->balance = -100;
        $wallet->save();

        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();

        // Balance turned negative 2 hours ago, expect mail sent
        $wallet->setSetting('balance_negative_since', $now->subHours(2)->toDateTimeString());
        $wallet->setSetting('balance_warning_initial', null);

        $job = new WalletCheck($wallet);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalance::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalance::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();

        // Test the migration scenario where a negative wallet has no balance_negative_since set yet
        Mail::fake();
        $wallet->setSetting('balance_negative_since', null);
        $wallet->setSetting('balance_warning_initial', null);

        $job = new WalletCheck($wallet);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalance::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalance::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        $wallet->refresh();
        $today_regexp = '/' . Carbon::now()->toDateString() . ' [0-9]{2}:[0-9]{2}:[0-9]{2}/';
        $this->assertRegExp($today_regexp, $wallet->getSetting('balance_negative_since'));
        $this->assertRegExp($today_regexp, $wallet->getSetting('balance_warning_initial'));
    }

    /**
     * Test job handle, top-up before reminder notification
     *
     * @depends testHandleInitial
     */
    public function testHandleBeforeReminder(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance turned negative 7-1 days ago
        $wallet->setSetting('balance_negative_since', $now->subDays(7 - 1)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $res = $job->handle();

        Mail::assertNothingSent();

        // TODO: Test that it actually executed the topUpWallet()
        $this->assertSame(WalletCheck::THRESHOLD_BEFORE_REMINDER, $res);
        $this->assertFalse($user->fresh()->isSuspended());
    }

    /**
     * Test job handle, reminder notification
     *
     * @depends testHandleBeforeReminder
     */
    public function testHandleReminder(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $user->setSetting('external_email', 'external@test.com');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance turned negative 7+1 days ago, expect mail sent
        $wallet->setSetting('balance_negative_since', $now->subDays(7 + 1)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalanceReminder::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalanceReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, top-up wallet before account suspending
     *
     * @depends testHandleReminder
     */
    public function testHandleBeforeSuspended(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance turned negative 7+14-1 days ago
        $days = 7 + 14 - 1;
        $wallet->setSetting('balance_negative_since', $now->subDays($days)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $res = $job->handle();

        Mail::assertNothingSent();

        // TODO: Test that it actually executed the topUpWallet()
        $this->assertSame(WalletCheck::THRESHOLD_BEFORE_SUSPEND, $res);
        $this->assertFalse($user->fresh()->isSuspended());
    }

    /**
     * Test job handle, account suspending
     *
     * @depends testHandleBeforeSuspended
     */
    public function testHandleSuspended(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $user->setSetting('external_email', 'external@test.com');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance turned negative 7+14+1 days ago, expect mail sent
        $days = 7 + 14 + 1;
        $wallet->setSetting('balance_negative_since', $now->subDays($days)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalanceSuspended::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalanceSuspended::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        // Check that it has been suspended
        $this->assertTrue($user->fresh()->isSuspended());

        // TODO: Test that group account members/domain are also being suspended
        /*
        foreach ($wallet->entitlements()->fresh()->get() as $entitlement) {
            if (
                $entitlement->entitleable_type == \App\Domain::class
                || $entitlement->entitleable_type == \App\User::class
            ) {
                $this->assertTrue($entitlement->entitleable->isSuspended());
            }
        }
        */

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, final warning before delete
     *
     * @depends testHandleSuspended
     */
    public function testHandleBeforeDelete(): void
    {
        Mail::fake();

        $user = $this->getTestUser('ned@kolab.org');
        $user->setSetting('external_email', 'external@test.com');
        $wallet = $user->wallets()->first();
        $now = Carbon::now();

        // Balance turned negative 7+14+21-3+1 days ago, expect mail sent
        $days = 7 + 14 + 21 - 3 + 1;
        $wallet->setSetting('balance_negative_since', $now->subDays($days)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $job->handle();

        // Assert the mail was sent to the user's email, and his external email
        Mail::assertSent(\App\Mail\NegativeBalanceBeforeDelete::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalanceBeforeDelete::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->hasCc('external@test.com');
        });

        // Check that it has not been deleted yet
        $this->assertFalse($user->fresh()->isDeleted());

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, account delete
     *
     * @depends testHandleBeforeDelete
     */
    public function testHandleDelete(): void
    {
        Mail::fake();

        $user = $this->getTestUser('wallet-check@kolabnow.com');
        $wallet = $user->wallets()->first();
        $wallet->balance = -100;
        $wallet->save();
        $now = Carbon::now();

        $package = \App\Package::where('title', 'kolab')->first();
        $user->assignPackage($package);

        $this->assertFalse($user->isDeleted());
        $this->assertCount(4, $user->entitlements()->get());

        // Balance turned negative 7+14+21+1 days ago, expect mail sent
        $days = 7 + 14 + 21 + 1;
        $wallet->setSetting('balance_negative_since', $now->subDays($days)->toDateTimeString());

        $job = new WalletCheck($wallet);
        $job->handle();

        Mail::assertNothingSent();

        // Check that it has not been deleted
        $this->assertTrue($user->fresh()->trashed());
        $this->assertCount(0, $user->entitlements()->get());

        // TODO: Test it deletes all members of the group account
    }
}
