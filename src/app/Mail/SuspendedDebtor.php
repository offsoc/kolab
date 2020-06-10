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

        $moreInfoHtml = null;
        $moreInfoText = null;
        if ($moreInfoUrl = \config('app.kb.account_suspended')) {
            $moreInfoHtml = \trans('mail.more-info-html', ['href' => $moreInfoUrl]);
            $moreInfoText = \trans('mail.more-info-text', ['href' => $moreInfoUrl]);
        }

        $this->view('emails.html.suspended_debtor')
            ->text('emails.plain.suspended_debtor')
            ->subject($subject)
            ->with([
                    'site' => \config('app.name'),
                    'subject' => $subject,
                    'username' => $user->name(true),
                    'cancelUrl' => \config('app.kb.account_delete'),
                    'supportUrl' => \config('app.support_url'),
                    'walletUrl' => Utils::serviceUrl('/wallet'),
                    'moreInfoHtml' => $moreInfoHtml,
                    'moreInfoText' => $moreInfoText,
                    'days' => 14 // TODO: Configurable
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
        $user = new User();

        $mail = new self($user);

        return Helper::render($mail, $type);
    }
}
