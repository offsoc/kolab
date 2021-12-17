<?php

namespace App\Jobs;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WalletCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const THRESHOLD_DEGRADE = 'degrade';
    public const THRESHOLD_DEGRADE_REMINDER = 'degrade-reminder';
    public const THRESHOLD_BEFORE_DEGRADE = 'before_degrade';
    public const THRESHOLD_DELETE = 'delete';
    public const THRESHOLD_BEFORE_DELETE = 'before_delete';
    public const THRESHOLD_SUSPEND = 'suspend';
    public const THRESHOLD_BEFORE_SUSPEND = 'before_suspend';
    public const THRESHOLD_REMINDER = 'reminder';
    public const THRESHOLD_BEFORE_REMINDER = 'before_reminder';
    public const THRESHOLD_INITIAL = 'initial';

    /** @var int The number of seconds to wait before retrying the job. */
    public $retryAfter = 10;

    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var \App\Wallet A wallet object */
    protected $wallet;


    /**
     * Create a new job instance.
     *
     * @param \App\Wallet $wallet The wallet that has been charged.
     *
     * @return void
     */
    public function __construct(Wallet $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Execute the job.
     *
     * @return ?string Executed action (THRESHOLD_*)
     */
    public function handle()
    {
        if ($this->wallet->balance >= 0) {
            return null;
        }

        $now = Carbon::now();
/*
        // Steps for old "first suspend then delete" approach
        $steps = [
            // Send the initial reminder
            self::THRESHOLD_INITIAL => 'initialReminder',
            // Try to top-up the wallet before the second reminder
            self::THRESHOLD_BEFORE_REMINDER => 'topUpWallet',
            // Send the second reminder
            self::THRESHOLD_REMINDER => 'secondReminder',
            // Try to top-up the wallet before suspending the account
            self::THRESHOLD_BEFORE_SUSPEND => 'topUpWallet',
            // Suspend the account
            self::THRESHOLD_SUSPEND => 'suspendAccount',
            // Warn about the upcomming account deletion
            self::THRESHOLD_BEFORE_DELETE => 'warnBeforeDelete',
            // Delete the account
            self::THRESHOLD_DELETE => 'deleteAccount',
        ];
*/
        // Steps for "demote instead of suspend+delete" approach
        $steps = [
            // Send the initial reminder
            self::THRESHOLD_INITIAL => 'initialReminderForDegrade',
            // Try to top-up the wallet before the second reminder
            self::THRESHOLD_BEFORE_REMINDER => 'topUpWallet',
            // Send the second reminder
            self::THRESHOLD_REMINDER => 'secondReminderForDegrade',
            // Try to top-up the wallet before the account degradation
            self::THRESHOLD_BEFORE_DEGRADE => 'topUpWallet',
            // Degrade the account
            self::THRESHOLD_DEGRADE => 'degradeAccount',
        ];

        if ($this->wallet->owner && $this->wallet->owner->isDegraded()) {
            $this->degradedReminder();
            return self::THRESHOLD_DEGRADE_REMINDER;
        }

        foreach (array_reverse($steps, true) as $type => $method) {
            if (self::threshold($this->wallet, $type) < $now) {
                $this->{$method}();
                return $type;
            }
        }

        return null;
    }

    /**
     * Send the initial reminder (for the suspend+delete process)
     */
    protected function initialReminder()
    {
        if ($this->wallet->getSetting('balance_warning_initial')) {
            return;
        }

        // TODO: Should we check if the account is already suspended?

        $this->sendMail(\App\Mail\NegativeBalance::class, false);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_initial', $now);
    }

    /**
     * Send the initial reminder (for the process of degrading a account)
     */
    protected function initialReminderForDegrade()
    {
        if ($this->wallet->getSetting('balance_warning_initial')) {
            return;
        }

        if (!$this->wallet->owner || $this->wallet->owner->isDegraded()) {
            return;
        }

        $this->sendMail(\App\Mail\NegativeBalance::class, false);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_initial', $now);
    }

    /**
     * Send the second reminder (for the suspend+delete process)
     */
    protected function secondReminder()
    {
        if ($this->wallet->getSetting('balance_warning_reminder')) {
            return;
        }

        // TODO: Should we check if the account is already suspended?

        $this->sendMail(\App\Mail\NegativeBalanceReminder::class, false);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_reminder', $now);
    }

    /**
     * Send the second reminder (for the process of degrading a account)
     */
    protected function secondReminderForDegrade()
    {
        if ($this->wallet->getSetting('balance_warning_reminder')) {
            return;
        }

        if (!$this->wallet->owner || $this->wallet->owner->isDegraded()) {
            return;
        }

        $this->sendMail(\App\Mail\NegativeBalanceReminderDegrade::class, true);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_reminder', $now);
    }

    /**
     * Suspend the account (and send the warning)
     */
    protected function suspendAccount()
    {
        if ($this->wallet->getSetting('balance_warning_suspended')) {
            return;
        }

        // Sanity check, already deleted
        if (!$this->wallet->owner) {
            return;
        }

        // Suspend the account
        $this->wallet->owner->suspend();
        foreach ($this->wallet->entitlements as $entitlement) {
            if (
                $entitlement->entitleable_type == \App\Domain::class
                || $entitlement->entitleable_type == \App\User::class
            ) {
                $entitlement->entitleable->suspend();
            }
        }

        $this->sendMail(\App\Mail\NegativeBalanceSuspended::class, true);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_suspended', $now);
    }

    /**
     * Send the last warning before delete
     */
    protected function warnBeforeDelete()
    {
        if ($this->wallet->getSetting('balance_warning_before_delete')) {
            return;
        }

        // Sanity check, already deleted
        if (!$this->wallet->owner) {
            return;
        }

        $this->sendMail(\App\Mail\NegativeBalanceBeforeDelete::class, true);

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_before_delete', $now);
    }

    /**
     * Send the periodic reminder to the degraded account owners
     */
    protected function degradedReminder()
    {
        // Sanity check
        if (!$this->wallet->owner || !$this->wallet->owner->isDegraded()) {
            return;
        }

        $now = \Carbon\Carbon::now();
        $last = $this->wallet->getSetting('degraded_last_reminder');

        if ($last) {
            $last = new Carbon($last);
            $period = 14;

            if ($last->addDays($period) > $now) {
                return;
            }

            $this->sendMail(\App\Mail\DegradedAccountReminder::class, true);
        }

        $this->wallet->setSetting('degraded_last_reminder', $now->toDateTimeString());
    }

    /**
     * Degrade the account
     */
    protected function degradeAccount()
    {
        // The account may be already deleted, or degraded
        if (!$this->wallet->owner || $this->wallet->owner->isDegraded()) {
            return;
        }

        $email = $this->wallet->owner->email;

        // The dirty work will be done by UserObserver
        $this->wallet->owner->degrade();

        \Log::info(
            sprintf(
                "[WalletCheck] Account degraded %s (%s)",
                $this->wallet->id,
                $email
            )
        );

        $this->sendMail(\App\Mail\NegativeBalanceDegraded::class, true);
    }

    /**
     * Delete the account
     */
    protected function deleteAccount()
    {
        // TODO: This will not work when we actually allow multiple-wallets per account
        //       but in this case we anyway have to change the whole thing
        //       and calculate summarized balance from all wallets.
        // The dirty work will be done by UserObserver
        if ($this->wallet->owner) {
            $email = $this->wallet->owner->email;

            $this->wallet->owner->delete();

            \Log::info(
                sprintf(
                    "[WalletCheck] Account deleted %s (%s)",
                    $this->wallet->id,
                    $email
                )
            );
        }
    }

    /**
     * Send the email
     *
     * @param string  $class         Mailable class name
     * @param bool    $with_external Use users's external email
     */
    protected function sendMail($class, $with_external = false): void
    {
        // TODO: Send the email to all wallet controllers?

        $mail = new $class($this->wallet, $this->wallet->owner);

        list($to, $cc) = \App\Mail\Helper::userEmails($this->wallet->owner, $with_external);

        if (!empty($to) || !empty($cc)) {
            $params = [
                'to' => $to,
                'cc' => $cc,
                'add' => " for {$this->wallet->id}",
            ];

            \App\Mail\Helper::sendMail($mail, $this->wallet->owner->tenant_id, $params);
        }
    }

    /**
     * Get the date-time for an action threshold. Calculated using
     * the date when a wallet balance turned negative.
     *
     * @param \App\Wallet $wallet A wallet
     * @param string      $type   Action type (one of self::THRESHOLD_*)
     *
     * @return \Carbon\Carbon The threshold date-time object
     */
    public static function threshold(Wallet $wallet, string $type): ?Carbon
    {
        $negative_since = $wallet->getSetting('balance_negative_since');

        // Migration scenario: balance<0, but no balance_negative_since set
        if (!$negative_since) {
            // 2h back from now, so first run can sent the initial notification
            $negative_since = Carbon::now()->subHours(2);
            $wallet->setSetting('balance_negative_since', $negative_since->toDateTimeString());
        } else {
            $negative_since = new Carbon($negative_since);
        }

        // Initial notification
        // Give it an hour so the async recurring payment has a chance to be finished
        if ($type == self::THRESHOLD_INITIAL) {
            return $negative_since->addHours(1);
        }

        $thresholds = [
            // A day before the second reminder
            self::THRESHOLD_BEFORE_REMINDER => 7 - 1,
            // Second notification
            self::THRESHOLD_REMINDER => 7,

            // A day before account suspension
            self::THRESHOLD_BEFORE_SUSPEND => 14 + 7 - 1,
            // Account suspension
            self::THRESHOLD_SUSPEND => 14 + 7,
            // Warning about the upcomming account deletion
            self::THRESHOLD_BEFORE_DELETE => 21 + 14 + 7 - 3,
            // Acount deletion
            self::THRESHOLD_DELETE => 21 + 14 + 7,

            // Last chance to top-up the wallet
            self::THRESHOLD_BEFORE_DEGRADE => 13,
            // Account degradation
            self::THRESHOLD_DEGRADE => 14,
        ];

        if (!empty($thresholds[$type])) {
            return $negative_since->addDays($thresholds[$type]);
        }

        return null;
    }

    /**
     * Try to automatically top-up the wallet
     */
    protected function topUpWallet(): void
    {
        PaymentsController::topUpWallet($this->wallet);
    }
}
