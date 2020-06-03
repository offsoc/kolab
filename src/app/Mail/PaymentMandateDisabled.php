<?php

namespace App\Mail;

use App\User;
use App\Utils;
use App\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentMandateDisabled extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\Wallet A wallet for which the mandate has been disabled */
    protected $wallet;

    /** @var \App\User A wallet controller to whom the email is being send */
    protected $user;


    /**
     * Create a new message instance.
     *
     * @param \App\Wallet $wallet A wallet that has been charged
     * @param \App\User   $user   An email recipient
     *
     * @return void
     */
    public function __construct(Wallet $wallet, User $user)
    {
        $this->wallet = $wallet;
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

        $subject = \trans('mail.paymentmandatedisabled-subject', ['site' => \config('app.name')]);

        $this->view('emails.payment_mandate_disabled')
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
        $wallet = new Wallet();
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        if (!\config('app.support_url')) {
            \config(['app.support_url' => 'https://not-configured-support.url']);
        }

        $mail = new self($wallet, $user);

        return $mail->build()->render();
    }
}
