<?php

namespace App\Mail;

use App\Payment;
use App\Tenant;
use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailure extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\Payment A payment operation */
    protected $payment;

    /** @var \App\User A wallet controller to whom the email is being send */
    protected $user;


    /**
     * Create a new message instance.
     *
     * @param \App\Payment $payment A payment operation
     * @param \App\User    $user    An email recipient
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

        $subject = \trans('mail.paymentfailure-subject', ['site' => $appName]);

        $this->view('emails.html.payment_failure')
            ->text('emails.plain.payment_failure')
            ->subject($subject)
            ->with([
                    'site' => $appName,
                    'subject' => $subject,
                    'username' => $this->user->name(true),
                    'walletUrl' => Utils::serviceUrl('/wallet', $this->user->tenant_id),
                    'supportUrl' => $supportUrl,
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
        $payment = new Payment();
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        $mail = new self($payment, $user);

        return Helper::render($mail, $type);
    }
}
