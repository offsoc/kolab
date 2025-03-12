<?php

namespace App\Jobs\Mail;

use App\Mail\PaymentMandateDisabled;
use App\User;
use App\Wallet;

class PaymentMandateDisabledJob extends \App\Jobs\MailJob
{
    /** @var \App\Wallet A wallet object */
    protected $wallet;

    /** @var ?\App\User A wallet controller */
    protected $controller;


    /**
     * Create a new job instance.
     *
     * @param \App\Wallet $wallet     A wallet object
     * @param \App\User   $controller An email recipient (wallet controller)
     *
     * @return void
     */
    public function __construct(Wallet $wallet, User $controller = null)
    {
        $this->wallet = $wallet;
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
        if (empty($this->controller)) {
            $this->controller = $this->wallet->owner;
        }

        if (empty($this->controller)) {
            return;
        }

        $mail = new PaymentMandateDisabled($this->wallet, $this->controller);

        list($to, $cc) = \App\Mail\Helper::userEmails($this->controller);

        if (!empty($to) || !empty($cc)) {
            $params = [
                'to' => $to,
                'cc' => $cc,
                'add' => " for {$this->wallet->id}",
            ];

            \App\Mail\Helper::sendMail($mail, $this->controller->tenant_id, $params);
        }

        /*
        // Send the email to all controllers too
        if ($this->controller->id == $this->wallet->owner->id) {
            $this->wallet->controllers->each(function ($controller) {
                self::dispatch($this->wallet, $controller);
            }
        });
        */
    }
}
