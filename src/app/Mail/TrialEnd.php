<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;

class TrialEnd extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @param \App\User $user An account owner
     *
     * @return void
     */
    public function __construct(User $user)
    {
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
        $paymentUrl = Tenant::getConfig($this->user->tenant_id, 'app.kb.payment_system');
        $supportUrl = Tenant::getConfig($this->user->tenant_id, 'app.support_url');

        $vars = [
            'name' => $this->user->name(true),
            'site' => $appName,
        ];

        $this->view('emails.html.trial_end')
            ->text('emails.plain.trial_end')
            ->subject(\trans('mail.trialend-subject', $vars))
            ->with([
                    'vars' => $vars,
                    'paymentUrl' => $paymentUrl,
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
    public static function fakeRender(string $type = 'html'): string
    {
        $user = new User();

        $mail = new self($user);

        return Helper::render($mail, $type);
    }
}
