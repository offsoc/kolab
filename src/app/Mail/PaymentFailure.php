<?php

namespace App\Mail;

use App\Payment;
use App\Tenant;
use App\User;
use App\Utils;

class PaymentFailure extends Mailable
{
    /** @var \App\Payment A payment operation */
    protected $payment;


    /**
     * Create a new message instance.
     *
     * @param \App\Payment $payment A payment operation
     * @param \App\User    $user    A wallet controller to whom the email is being sent
     *
     * @return void
     */
    public function __construct(Payment $payment, User $user)
    {
        $this->payment = $payment;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');
        $supportUrl = Tenant::getConfig($this->user->tenant_id, 'app.support_url');

        $vars = [
            'email' => $this->payment->wallet->owner->email,
            'name' => $this->user->name(true),
            'site' => $appName,
        ];

        $this->view('emails.html.payment_failure')
            ->text('emails.plain.payment_failure')
            ->subject(\trans('mail.paymentfailure-subject', $vars))
            ->with([
                    'vars' => $vars,
                    'walletUrl' => Utils::serviceUrl('/wallet', $this->user->tenant_id),
                    'supportUrl' => Utils::serviceUrl($supportUrl, $this->user->tenant_id),
            ]);

        return $this;
    }

    /**
     * Render the mail template with fake data
     *
     * @param string $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function fakeRender(string $type = 'mail'): string
    {
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        $payment = new Payment();
        $payment->wallet = new \App\Wallet();
        $payment->wallet->owner = $user;

        $mail = new self($payment, $user);

        return Helper::render($mail, $type);
    }
}
