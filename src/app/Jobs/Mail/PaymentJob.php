<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\PaymentFailure;
use App\Mail\PaymentSuccess;
use App\Payment;
use App\User;

class PaymentJob extends MailJob
{
    /** @var Payment A payment object */
    protected $payment;

    /** @var ?User A wallet controller */
    protected $controller;

    /**
     * Create a new job instance.
     *
     * @param Payment $payment    A payment object
     * @param User    $controller A wallet controller
     */
    public function __construct(Payment $payment, ?User $controller = null)
    {
        $this->payment = $payment;
        $this->controller = $controller;
    }

    /**
     * Execute the job.
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

        if ($this->payment->status == Payment::STATUS_PAID) {
            $mail = new PaymentSuccess($this->payment, $this->controller);
            $label = "Success";
        } elseif (
            $this->payment->status == Payment::STATUS_EXPIRED
            || $this->payment->status == Payment::STATUS_FAILED
        ) {
            $mail = new PaymentFailure($this->payment, $this->controller);
            $label = "Failure";
        } else {
            return;
        }

        [$to, $cc] = Helper::userEmails($this->controller);

        if (!empty($to) || !empty($cc)) {
            $params = [
                'to' => $to,
                'cc' => $cc,
                'add' => " for {$wallet->id}",
            ];

            Helper::sendMail($mail, $this->controller->tenant_id, $params);
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
