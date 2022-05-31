<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialEnd extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\User An account owner (account) */
    protected $account;


    /**
     * Create a new message instance.
     *
     * @param \App\User $account An account owner (account)
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
        $appName = Tenant::getConfig($this->account->tenant_id, 'app.name');
        $paymentUrl = Tenant::getConfig($this->account->tenant_id, 'app.kb.payment_system');
        $supportUrl = Tenant::getConfig($this->account->tenant_id, 'app.support_url');

        $subject = \trans('mail.trialend-subject', ['site' => $appName]);

        $this->view('emails.html.trial_end')
            ->text('emails.plain.trial_end')
            ->subject($subject)
            ->with([
                    'site' => $appName,
                    'subject' => $subject,
                    'username' => $this->account->name(true),
                    'paymentUrl' => $paymentUrl,
                    'supportUrl' => Utils::serviceUrl($supportUrl, $this->account->tenant_id),
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
