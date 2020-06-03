<?php

namespace App\Mail;

use App\Payment;
use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccess extends Mailable
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
        $user = $this->user;

        $subject = \trans('mail.paymentsuccess-subject', ['site' => \config('app.name')]);

        $this->view('emails.payment_success')
            ->subject($subject)
            ->with([
                    'site' => \config('app.name'),
                    'subject' => $subject,
                    'username' => $user->name(true),
                    'walletUrl' => Utils::serviceUrl('/wallet'),
                    'supportUrl' => \config('app.support_url'),
            ]);

        return $this;
    }

    /**
     * Render the mail template with fake data
     *
     * @return string HTML output
     */
    public static function fakeRender(): string
    {
        $payment = new Payment();
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        if (!\config('app.support_url')) {
            \config(['app.support_url' => 'https://not-configured-support.url']);
        }

        $mail = new self($payment, $user);

        return $mail->build()->render();
    }
}
