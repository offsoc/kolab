<?php

namespace Tests\Unit\Mail;

use App\Mail\PasswordReset;
use App\User;
use App\Utils;
use App\VerificationCode;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $code = new VerificationCode([
                'user_id' => 123456789,
                'mode' => 'password-reset',
                'code' => 'code',
                'short_code' => 'short-code',
        ]);

        $code->user = new User([
                'name' => 'User Name',
        ]);

        $mail = $this->fakeMail(new PasswordReset($code));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $url = Utils::serviceUrl('/password-reset/' . $code->short_code . '-' . $code->code);
        $link = "<a href=\"$url\">$url</a>";
        $appName = \config('app.name');

        $this->assertMailSubject("$appName Password Reset", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, $code->user->name(true)) > 0);

        $this->assertStringStartsWith("Dear " . $code->user->name(true), $plain);
        $this->assertTrue(strpos($plain, $link) > 0);
    }
}
