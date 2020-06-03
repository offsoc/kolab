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

        $this->view('emails.signup_code')
            ->subject(__('mail.signupcode-subject', ['site' => \config('app.name')]))
            ->with([
                    'site' => \config('app.name'),
                    'username' => $username ?: 'User',
                    'code' => $this->code->code,
                    'short_code' => $this->code->short_code,
                    'link' => sprintf('<a href="%s">%s</a>', $href, $href),
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

        return $mail->build()->render();
    }
}
