<?php

namespace Tests\Unit\Mail;

use App\Mail\SignupVerification;
use App\SignupCode;
use App\Utils;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class SignupVerificationTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $code = new SignupCode([
                'code' => 'code',
                'short_code' => 'short-code',
                'email' => 'test@email',
                'first_name' => 'First',
                'last_name' => 'Last',
        ]);

        $mail = $this->fakeMail(new SignupVerification($code));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $url = Utils::serviceUrl('/signup/' . $code->short_code . '-' . $code->code);
        $link = "<a href=\"$url\">$url</a>";
        $appName = \config('app.name');

        $this->assertMailSubject("$appName Registration", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, 'First Last') > 0);

        $this->assertStringStartsWith('Dear First Last', $plain);
        $this->assertTrue(strpos($plain, $url) > 0);
    }
}
