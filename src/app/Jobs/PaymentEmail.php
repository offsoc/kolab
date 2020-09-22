<?php

namespace App\Jobs;

use App\Payment;
use App\Providers\PaymentProvider;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class PaymentEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 2;

    /** @var int The number of seconds to wait before retrying the job. */
    public $retryAfter = 10;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var \App\Payment A payment object */
    protected $payment;

    /** @var \App\User A wallet controller */
    protected $controller;


    /**
     * Create a new job instance.
     *
     * @param \App\Payment $payment    A payment object
     * @param \App\User    $controller A wallet controller
     *
     * @return void
     */
    public function __construct(Payment $payment, User $controller = null)
    {
        $this->payment = $payment;
        $this->controller = $controller;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $wallet = $this->payment->wallet;

        if (empty($this->controller)) {
            $this->controller = $wallet->owner;
        }

        if (empty($this->controller)) {
            return;
        }

        if ($this->payment->status == PaymentProvider::STATUS_PAID) {
            $mail = new \App\Mail\PaymentSuccess($this->payment, $this->controller);
            $label = "Success";
        } elseif (
            $this->payment->status == PaymentProvider::STATUS_EXPIRED
            || $this->payment->status == PaymentProvider::STATUS_FAILED
        ) {
            $mail = new \App\Mail\PaymentFailure($this->payment, $this->controller);
            $label = "Failure";
        } else {
            return;
        }

        list($to, $cc) = \App\Mail\Helper::userEmails($this->controller);

        if (!empty($to)) {
            try {
                Mail::to($to)->cc($cc)->send($mail);

                $msg = sprintf(
                    "[Payment] %s mail sent for %s (%s)",
                    $label,
                    $wallet->id,
                    empty($cc) ? $to : implode(', ', array_merge([$to], $cc))
                );

                \Log::info($msg);
            } catch (\Exception $e) {
                $msg = sprintf(
                    "[Payment] Failed to send mail for wallet %s (%s): %s",
                    $wallet->id,
                    empty($cc) ? $to : implode(', ', array_merge([$to], $cc)),
                    $e->getMessage()
                );

                \Log::error($msg);
                throw $e;
            }
        }

        /*
        // Send the email to all wallet controllers too
        if ($wallet->owner->id == $this->controller->id) {
            $this->wallet->controllers->each(function ($controller) {
                self::dispatch($this->payment, $controller);
            }
        });
        */
    }
}
