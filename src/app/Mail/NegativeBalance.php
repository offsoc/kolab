<?php

namespace App\Mail;

use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NegativeBalance extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\User A user (account) that is behind with payments */
    protected $account;


    /**
     * Create a new message instance.
     *
     * @param \App\User $account A user (account)
     *
     * @return void
     */
    public function __construct(User $account)
    {
        $this->account = $account;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = $this->account;

        $subject = \trans('mail.negativebalance-subject', ['site' => \config('app.name')]);

        $this->view('emails.negative_balance')
            ->subject($subject)
            ->with([
                    'site' => \config('app.name'),
                    'subject' => $subject,
                    'username' => $user->name(true),
                    'supportUrl' => \config('app.support_url'),
                    'walletUrl' => Utils::serviceUrl('/wallet'),
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
        $user = new User();

        $mail = new self($user);

        return $mail->build()->render();
    }
}
