<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;

class PasswordExpirationReminder extends Mailable
{
    /** @var string Password expiration date */
    protected $expiresOn;


    /**
     * Create a new message instance.
     *
     * @param \App\User $user      A user object
     * @param string    $expiresOn Password expiration date (Y-m-d)
     *
     * @return void
     */
    public function __construct(User $user, string $expiresOn)
    {
        $this->user = $user;
        $this->expiresOn = $expiresOn;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');
        $href = Utils::serviceUrl('profile', $this->user->tenant_id);

        $vars = [
            'date' => $this->expiresOn,
            'email' => $this->user->email,
            'name' => $this->user->name(true),
            'site' => $appName,
        ];

        $this->view('emails.html.password_expiration_reminder')
            ->text('emails.plain.password_expiration_reminder')
            ->subject(\trans('mail.passwordexpiration-subject', $vars))
            ->with([
                    'vars' => $vars,
                    'link' => sprintf('<a href="%s">%s</a>', $href, $href)
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
        $user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        $mail = new self($user, now()->copy()->addDays(14)->toDateString());

        return Helper::render($mail, $type);
    }

    /**
     * Returns the mail subject.
     *
     * @return string Mail subject
     */
    public function getSubject(): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');

        $params = [
            'site' => $appName,
            'date' => $this->expiresOn,
        ];

        return \trans('mail.passwordexpiration-subject', $params);
    }
}
