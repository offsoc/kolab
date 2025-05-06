<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\PaymentMandateDisabled;
use App\User;
use App\Wallet;

class PaymentMandateDisabledJob extends MailJob
{
    /** @var Wallet A wallet object */
    protected $wallet;

    /** @var ?User A wallet controller */
    protected $controller;

    /**
     * Create a new job instance.
     *
     * @param Wallet $wallet     A wallet object
     * @param User   $controller An email recipient (wallet controller)
     */
    public function __construct(Wallet $wallet, ?User $controller = null)
    {
        $this->wallet = $wallet;
        $this->controller = $controller;
    }

    /**
     * Execute the job.
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

        [$to, $cc] = Helper::userEmails($this->controller);

        if (!empty($to) || !empty($cc)) {
            $params = [
                'to' => $to,
                'cc' => $cc,
                'add' => " for {$this->wallet->id}",
            ];

            Helper::sendMail($mail, $this->controller->tenant_id, $params);
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
