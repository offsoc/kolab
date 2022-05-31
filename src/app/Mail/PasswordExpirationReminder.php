<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PasswordExpirationReminder extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\User The user object */
    protected $user;

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

        $params = [
            'site' => $appName,
            'date' => $this->expiresOn,
            'link' => sprintf('<a href="%s">%s</a>', $href, $href),
            'username' => $this->user->name(true),
        ];

        $this->view('emails.html.password_expiration_reminder')
            ->text('emails.plain.password_expiration_reminder')
            ->subject(\trans('mail.passwordexpiration-subject', $params))
            ->with($params);

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
}
