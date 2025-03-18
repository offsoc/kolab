<?php

namespace App\Jobs;

use App\Wallet;
use Carbon\Carbon;

class WalletCheck extends CommonJob
{
    public const THRESHOLD_DEGRADE = 'degrade';
    public const THRESHOLD_DEGRADE_REMINDER = 'degrade-reminder';
    public const THRESHOLD_REMINDER = 'reminder';
    public const THRESHOLD_INITIAL = 'initial';

    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var string|null The name of the queue the job should be sent to. */
    public $queue = \App\Enums\Queue::Background->value;

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

        $this->wallet->chargeEntitlements();
        try {
            $this->wallet->topUp();
        } catch (\Exception $e) {
            \Log::error("Failed to top-up wallet {$this->walletId}: " . $e->getMessage());
            // Notification emails should be sent even if the top-up fails
        }

        if ($this->wallet->balance >= 0) {
            return null;
        }

        $now = Carbon::now();

        $steps = [
            // Send the initial reminder
            self::THRESHOLD_INITIAL => 'initialReminderForDegrade',
            // Send the second reminder
            self::THRESHOLD_REMINDER => 'secondReminderForDegrade',
            // Degrade the account
            self::THRESHOLD_DEGRADE => 'degradeAccount',
        ];

        if ($this->wallet->owner->isDegraded()) {
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

        if ($this->wallet->owner->isDegraded()) {
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

        if ($this->wallet->owner->isDegraded()) {
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
        if ($this->wallet->owner->isDegraded()) {
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
        if (!$this->wallet->owner->isDegraded()) {
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
            // Second notification
            self::THRESHOLD_REMINDER => 7,
            // Account degradation
            self::THRESHOLD_DEGRADE => 14,
        ];

        if (!empty($thresholds[$type])) {
            return $negative_since->addDays($thresholds[$type]);
        }

        return null;
    }
}
