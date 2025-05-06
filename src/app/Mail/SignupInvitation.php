<?php

namespace App\Mail;

use App\SignupInvitation as SI;
use App\Tenant;
use App\Utils;

class SignupInvitation extends Mailable
{
    /** @var SI A signup invitation object */
    protected $invitation;

    /**
     * Create a new message instance.
     *
     * @param SI $invitation A signup invitation object
     */
    public function __construct(SI $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = Tenant::getConfig($this->invitation->tenant_id, 'app.name');

        $href = Utils::serviceUrl('/signup/invite/' . $this->invitation->id, $this->invitation->tenant_id);

        $vars = [
            'site' => $appName,
        ];

        $this->view('emails.html.signup_invitation')
            ->text('emails.plain.signup_invitation')
            ->subject(\trans('mail.signupinvitation-subject', $vars))
            ->with([
                'vars' => $vars,
                'href' => $href,
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
        $invitation = new SI([
            'email' => 'test@external.org',
        ]);

        $invitation->id = Utils::uuidStr();

        $mail = new self($invitation);

        return Helper::render($mail, $type);
    }
}
