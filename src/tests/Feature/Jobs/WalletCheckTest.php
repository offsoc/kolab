<?php

namespace Tests\Feature\Jobs;

use App\Jobs\WalletCheck;
use App\User;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletCheckTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::createFromDate(2022, 02, 02));

        $this->deleteTestUser('wallet-check@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('wallet-check@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test job handle, initial negative-balance notification
     */
    public function testHandleInitial(): void
    {
        Mail::fake();

        $user = $this->prepareTestUser($wallet);
        $now = Carbon::now();

        $wallet->balance = 0;
        $wallet->save();

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();

        // Balance is negative now
        $wallet->balance = -100;
        $wallet->save();

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();

        // Balance turned negative 2 hours ago, expect mail sent
        $wallet->setSetting('balance_negative_since', $now->subHours(2)->toDateTimeString());
        $wallet->setSetting('balance_warning_initial', null);

        $job = new WalletCheck($wallet->id);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalance::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalance::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();

        // Test the migration scenario where a negative wallet has no balance_negative_since set yet
        Mail::fake();
        $wallet->setSetting('balance_negative_since', null);
        $wallet->setSetting('balance_warning_initial', null);

        $job = new WalletCheck($wallet->id);
        $job->handle();

        // Assert the mail was sent to the user's email, but not to his external email
        Mail::assertSent(\App\Mail\NegativeBalance::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalance::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        $wallet->refresh();
        $today_regexp = '/' . Carbon::now()->toDateString() . ' [0-9]{2}:[0-9]{2}:[0-9]{2}/';
        $this->assertMatchesRegularExpression($today_regexp, $wallet->getSetting('balance_negative_since'));
        $this->assertMatchesRegularExpression($today_regexp, $wallet->getSetting('balance_warning_initial'));

        // Test suspended user - no mail sent
        Mail::fake();
        $wallet->owner->suspend();
        $wallet->setSetting('balance_warning_initial', null);

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, wallet charge and top-up
     */
    public function testHandleInitialCharge(): void
    {
        Mail::fake();
        Queue::fake();

        $user = $this->prepareTestUser($wallet);
        $wallet->balance = 0;
        $wallet->save();
        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subWeeks(5));

        $job = new WalletCheck($wallet->id);
        $job->handle();

        $wallet->refresh();

        $this->assertTrue($wallet->balance < 0); // @phpstan-ignore-line

        // TODO: How to mock PaymentsProvider::topUpWallet() to make sure it was called?
        // We should probably move this method to the App\Wallet class to make it possible.
        $this->markTestIncomplete();
    }

    /**
     * Test job handle, reminder notification
     */
    public function testHandleReminder(): void
    {
        Mail::fake();

        $user = $this->prepareTestUser($wallet);
        $now = Carbon::now();

        // Balance turned negative 7+1 days ago, expect mail sent
        $wallet->setSetting('balance_negative_since', $now->subDays(7 + 1)->toDateTimeString());

        $job = new WalletCheck($wallet->id);
        $job->handle();

        // Assert the mail was sent to the user's email and to his external email
        Mail::assertSent(\App\Mail\NegativeBalanceReminderDegrade::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalanceReminderDegrade::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->hasCc('external@test.com');
        });

        // Run the job again to make sure the notification is not sent again
        Mail::fake();
        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();

        // Test suspended user - no mail sent
        Mail::fake();
        $wallet->owner->suspend();
        $wallet->setSetting('balance_warning_reminder', null);

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, account degrade
     *
     * @depends testHandleReminder
     */
    public function testHandleDegrade(): void
    {
        Mail::fake();

        $user = $this->prepareTestUser($wallet);
        $now = Carbon::now();

        $this->assertFalse($user->isDegraded());

        // Balance turned negative 7+7+1 days ago, expect mail sent
        $days = 7 + 7 + 1;
        $wallet->setSetting('balance_negative_since', $now->subDays($days)->toDateTimeString());

        $job = new WalletCheck($wallet->id);
        $job->handle();

        // Assert the mail was sent to the user's email, and his external email
        Mail::assertSent(\App\Mail\NegativeBalanceDegraded::class, 1);
        Mail::assertSent(\App\Mail\NegativeBalanceDegraded::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->hasCc('external@test.com');
        });

        // Check that it has been degraded
        $this->assertTrue($user->fresh()->isDegraded());

        // Test suspended user - no mail sent
        Mail::fake();
        $wallet->owner->suspend();
        $wallet->owner->undegrade();

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * Test job handle, periodic reminder to a degraded account
     *
     * @depends testHandleDegrade
     */
    public function testHandleDegradeReminder(): void
    {
        Mail::fake();

        $user = $this->prepareTestUser($wallet);
        $user->update(['status' => $user->status | User::STATUS_DEGRADED]);
        $now = Carbon::now();

        $this->assertTrue($user->isDegraded());

        // Test degraded_last_reminder not set
        $wallet->setSetting('degraded_last_reminder', null);

        $job = new WalletCheck($wallet->id);
        $res = $job->handle();

        Mail::assertNothingSent();

        $_last = Wallet::find($wallet->id)->getSetting('degraded_last_reminder');
        $this->assertSame(Carbon::now()->toDateTimeString(), $_last);
        $this->assertSame(WalletCheck::THRESHOLD_DEGRADE_REMINDER, $res);

        // Test degraded_last_reminder set, but 14 days didn't pass yet
        $last = $now->copy()->subDays(10);
        $wallet->setSetting('degraded_last_reminder', $last->toDateTimeString());

        $job = new WalletCheck($wallet->id);
        $res = $job->handle();

        Mail::assertNothingSent();

        $_last = $wallet->fresh()->getSetting('degraded_last_reminder');
        $this->assertSame(WalletCheck::THRESHOLD_DEGRADE_REMINDER, $res);
        $this->assertSame($last->toDateTimeString(), $_last);

        // Test degraded_last_reminder set, and 14 days passed
        $wallet->setSetting('degraded_last_reminder', $now->copy()->subDays(14)->setSeconds(0));

        $job = new WalletCheck($wallet->id);
        $res = $job->handle();

        // Assert the mail was sent to the user's email, and his external email
        Mail::assertSent(\App\Mail\DegradedAccountReminder::class, 1);
        Mail::assertSent(\App\Mail\DegradedAccountReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !$mail->hasCc('external@test.com');
        });

        $_last = $wallet->fresh()->getSetting('degraded_last_reminder');
        $this->assertSame(Carbon::now()->toDateTimeString(), $_last);
        $this->assertSame(WalletCheck::THRESHOLD_DEGRADE_REMINDER, $res);

        // Test suspended user - no mail sent
        Mail::fake();
        $wallet->owner->suspend();
        $wallet->owner->undegrade();
        $wallet->setSetting('degraded_last_reminder', null);

        $job = new WalletCheck($wallet->id);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * A helper to prepare a user for tests
     */
    private function prepareTestUser(&$wallet)
    {
        $status = User::STATUS_ACTIVE | User::STATUS_LDAP_READY | User::STATUS_IMAP_READY;
        $user = $this->getTestUser('wallet-check@kolabnow.com', ['status' => $status]);
        $user->setSetting('external_email', 'external@test.com');
        $wallet = $user->wallets()->first();

        $package = \App\Package::withObjectTenantContext($user)->where('title', 'kolab')->first();
        $user->assignPackage($package);

        $wallet->balance = -100;
        $wallet->save();

        return $user;
    }
}
