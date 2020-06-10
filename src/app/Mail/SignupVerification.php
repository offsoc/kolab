<?php

namespace App\Mail;

use App\SignupCode;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SignupVerification extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var SignupCode A signup verification code object */
    protected $code;


    /**
     * Create a new message instance.
     *
     * @param SignupCode $code A signup verification code object
     *
     * @return void
     */
    public function __construct(SignupCode $code)
    {
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $href = Utils::serviceUrl(
            sprintf('/signup/%s-%s', $this->code->short_code, $this->code->code)
        );

        $username = $this->code->data['first_name'] ?? '';
        if (!empty($this->code->data['last_name'])) {
            $username .= ' ' . $this->code->data['last_name'];
        }
        $username = trim($username);

        $this->view('emails.html.signup_code')
            ->text('emails.plain.signup_code')
            ->subject(__('mail.signupcode-subject', ['site' => \config('app.name')]))
            ->with([
                    'site' => \config('app.name'),
                    'username' => $username ?: 'User',
                    'code' => $this->code->code,
                    'short_code' => $this->code->short_code,
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
        $code = new SignupCode([
                'code' => Str::random(SignupCode::CODE_LENGTH),
                'short_code' => SignupCode::generateShortCode(),
                'data' => [
                    'email' => 'test@' . \config('app.domain'),
                    'first_name' => 'Firstname',
                    'last_name' => 'Lastname',
                ],
        ]);

        $mail = new self($code);

        return Helper::render($mail, $type);
    }
}
