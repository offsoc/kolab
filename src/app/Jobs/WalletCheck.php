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

        // Delete the account
        if (self::threshold($this->wallet, self::THRESHOLD_DELETE) < $now) {
            $this->deleteAccount();
            return self::THRESHOLD_DELETE;
        }

        // Warn about the upcomming account deletion
        if (self::threshold($this->wallet, self::THRESHOLD_BEFORE_DELETE) < $now) {
            $this->warnBeforeDelete();
            return self::THRESHOLD_BEFORE_DELETE;
        }

        // Suspend the account
        if (self::threshold($this->wallet, self::THRESHOLD_SUSPEND) < $now) {
            $this->suspendAccount();
            return self::THRESHOLD_SUSPEND;
        }

        // Try to top-up the wallet before suspending the account
        if (self::threshold($this->wallet, self::THRESHOLD_BEFORE_SUSPEND) < $now) {
            PaymentsController::topUpWallet($this->wallet);
            return self::THRESHOLD_BEFORE_SUSPEND;
        }

        // Send the second reminder
        if (self::threshold($this->wallet, self::THRESHOLD_REMINDER) < $now) {
            $this->secondReminder();
            return self::THRESHOLD_REMINDER;
        }

        // Try to top-up the wallet before the second reminder
        if (self::threshold($this->wallet, self::THRESHOLD_BEFORE_REMINDER) < $now) {
            PaymentsController::topUpWallet($this->wallet);
            return self::THRESHOLD_BEFORE_REMINDER;
        }

        // Send the initial reminder
        if (self::threshold($this->wallet, self::THRESHOLD_INITIAL) < $now) {
            $this->initialReminder();
            return self::THRESHOLD_INITIAL;
        }

        return null;
    }

    /**
     * Send the initial reminder
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
     * Send the second reminder
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

        $remind = 7;   // remind after first X days
        $suspend = 14; // suspend after next X days
        $delete = 21;  // delete after next X days
        $warn = 3;     // warn about delete on X days before delete

        // Acount deletion
        if ($type == self::THRESHOLD_DELETE) {
            return $negative_since->addDays($delete + $suspend + $remind);
        }

        // Warning about the upcomming account deletion
        if ($type == self::THRESHOLD_BEFORE_DELETE) {
            return $negative_since->addDays($delete + $suspend + $remind - $warn);
        }

        // Account suspension
        if ($type == self::THRESHOLD_SUSPEND) {
            return $negative_since->addDays($suspend + $remind);
        }

        // A day before account suspension
        if ($type == self::THRESHOLD_BEFORE_SUSPEND) {
            return $negative_since->addDays($suspend + $remind - 1);
        }

        // Second notification
        if ($type == self::THRESHOLD_REMINDER) {
            return $negative_since->addDays($remind);
        }

        // A day before the second reminder
        if ($type == self::THRESHOLD_BEFORE_REMINDER) {
            return $negative_since->addDays($remind - 1);
        }

        // Initial notification
        // Give it an hour so the async recurring payment has a chance to be finished
        if ($type == self::THRESHOLD_INITIAL) {
            return $negative_since->addHours(1);
        }

        return null;
    }
}
