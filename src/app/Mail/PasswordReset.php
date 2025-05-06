<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;
use App\VerificationCode;
use Illuminate\Support\Str;

class PasswordReset extends Mailable
{
    /** @var VerificationCode A verification code object */
    protected $code;

    /**
     * Create a new message instance.
     *
     * @param VerificationCode $code A verification code object
     */
    public function __construct(VerificationCode $code)
    {
        $this->code = $code;
        $this->user = $this->code->user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');

        $href = Utils::serviceUrl(
            sprintf('/password-reset/%s-%s', $this->code->short_code, $this->code->code),
            $this->user->tenant_id
        );

        $vars = [
            'email' => $this->user->email,
            'name' => $this->user->name(true),
            'site' => $appName,
        ];

        $this->view('emails.html.password_reset')
            ->text('emails.plain.password_reset')
            ->subject(\trans('mail.passwordreset-subject', $vars))
            ->with([
                'vars' => $vars,
                'link' => sprintf('<a href="%s">%s</a>', $href, $href),
                'code' => $this->code->code,
                'short_code' => $this->code->short_code,
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
        $code = new VerificationCode([
            'code' => Str::random(VerificationCode::CODE_LENGTH),
            'short_code' => VerificationCode::generateShortCode(),
        ]);

        // @phpstan-ignore-next-line
        $code->user = new User([
            'email' => 'test@' . \config('app.domain'),
        ]);

        $mail = new self($code);

        return Helper::render($mail, $type);
    }
}
