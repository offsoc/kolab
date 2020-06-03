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

        $ext_email = $this->controller->getSetting('external_email');
        $cc = [];

        if ($ext_email && $ext_email != $this->controller->email) {
            $cc[] = $ext_email;
        }

        if ($this->payment->status == PaymentProvider::STATUS_PAID) {
            $mail = new \App\Mail\PaymentSuccess($this->payment, $this->controller);
        } elseif (
            $this->payment->status == PaymentProvider::STATUS_EXPIRED
            || $this->payment->status == PaymentProvider::STATUS_FAILED
        ) {
            $mail = new \App\Mail\PaymentFailure($this->payment, $this->controller);
        } else {
            return;
        }

        Mail::to($this->controller->email)->cc($cc)->send($mail);

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
