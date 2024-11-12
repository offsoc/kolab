<?php

namespace App\Jobs;

use App\Http\Controllers\API\V4\PaymentsController;
use App\Wallet;
use Carbon\Carbon;

class WalletCheck extends CommonJob
{
    public const THRESHOLD_DEGRADE = 'degrade';
    public const THRESHOLD_DEGRADE_REMINDER = 'degrade-reminder';
    public const THRESHOLD_BEFORE_DEGRADE = 'before-degrade';
    public const THRESHOLD_REMINDER = 'reminder';
    public const THRESHOLD_BEFORE_REMINDER = 'before-reminder';
    public const THRESHOLD_INITIAL = 'initial';

    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var ?Wallet A wallet object */
    protected $wallet;

    /** @var string A wallet identifier */
    protected $walletId;


    /**
     * Create a new job instance.
     *
     * @param string $walletId The wallet that has been charged.
     *
     * @return void
     */
    public function __construct(string $walletId)
    {
        $this->walletId = $walletId;
    }

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    /**
     * Execute the job.
     *
     * @return ?string Executed action (THRESHOLD_*)
     */
    public function handle()
    {
        $this->logJobStart($this->walletId);

        $this->wallet = Wallet::find($this->walletId);

        // Sanity check (owner deleted in meantime)
        if (!$this->wallet || !$this->wallet->owner) {
            \Log::warning(
                "[WalletCheck] The wallet has been deleted in the meantime or doesn't have an owner {$this->walletId}."
            );
            return null;
        }

        if ($this->wallet->chargeEntitlements() > 0) {
            // We make a payment when there's a charge. If for some reason the
            // payment failed we can't just throw here, as another execution of this job
            // will not re-try the payment. So, we attempt a payment in a separate job.
            try {
                $this->topUpWallet();
            } catch (\Exception $e) {
                \Log::error("Failed to top-up wallet {$this->walletId}: " . $e->getMessage());
                WalletCharge::dispatch($this->wallet->id);
            }
        }

        if ($this->wallet->balance >= 0) {
            return null;
        }

        $now = Carbon::now();

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

        if (!$this->wallet->owner->isSuspended()) {
            $this->sendMail(\App\Mail\NegativeBalance::class, false);
        }

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_initial', $now);
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

        if (!$this->wallet->owner->isSuspended()) {
            $this->sendMail(\App\Mail\NegativeBalanceReminderDegrade::class, true);
        }

        $now = \Carbon\Carbon::now()->toDateTimeString();
        $this->wallet->setSetting('balance_warning_reminder', $now);
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

        if (!$this->wallet->owner->isSuspended()) {
            $this->sendMail(\App\Mail\NegativeBalanceDegraded::class, true);
        }
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

        if ($this->wallet->owner->isSuspended()) {
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

            $this->sendMail(\App\Mail\DegradedAccountReminder::class, false);
        }

        $this->wallet->setSetting('degraded_last_reminder', $now->toDateTimeString());
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
