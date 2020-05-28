<?php

namespace App\Mail;

use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuspendedDebtor extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\User A suspended user (account) */
    protected $account;


    /**
     * Create a new message instance.
     *
     * @param \App\User $account A suspended user (account)
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

        $subject = \trans('mail.suspendeddebtor-subject', ['site' => \config('app.name')]);

        $moreInfo = null;
        if ($moreInfoUrl = \config('app.kb.account_suspended')) {
            $moreInfo = \trans('mail.more-info-html', ['href' => $moreInfoUrl]);
        }

        $this->view('emails.suspended_debtor')
            ->subject($subject)
            ->with([
                    'site' => \config('app.name'),
                    'subject' => $subject,
                    'username' => $user->name(true),
                    'cancelUrl' => \config('app.kb.account_delete'),
                    'supportUrl' => \config('app.support_url'),
                    'walletUrl' => Utils::serviceUrl('/wallet'),
                    'moreInfo' => $moreInfo,
                    'days' => 14 // TODO: Configurable
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
