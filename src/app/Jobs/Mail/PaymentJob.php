<?php

namespace App\Jobs\Mail;

use App\Payment;
use App\User;

class PaymentJob extends \App\Jobs\MailJob
{
    /** @var \App\Payment A payment object */
    protected $payment;

    /** @var ?\App\User A wallet controller */
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
        $this->onQueue(self::QUEUE);
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

        if ($this->payment->status == Payment::STATUS_PAID) {
            $mail = new \App\Mail\PaymentSuccess($this->payment, $this->controller);
            $label = "Success";
        } elseif (
            $this->payment->status == Payment::STATUS_EXPIRED
            || $this->payment->status == Payment::STATUS_FAILED
        ) {
            $mail = new \App\Mail\PaymentFailure($this->payment, $this->controller);
            $label = "Failure";
        } else {
            return;
        }

        list($to, $cc) = \App\Mail\Helper::userEmails($this->controller);

        if (!empty($to) || !empty($cc)) {
            $params = [
                'to' => $to,
                'cc' => $cc,
                'add' => " for {$wallet->id}",
            ];

            \App\Mail\Helper::sendMail($mail, $this->controller->tenant_id, $params);
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
