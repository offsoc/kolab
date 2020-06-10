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

        $this->view('emails.html.payment_success')
            ->text('emails.plain.payment_success')
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
     * @param string $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function fakeRender(string $type = 'html'): string
    {
        $payment = new Payment();
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        $mail = new self($payment, $user);

        return Helper::render($mail, $type);
    }
}
