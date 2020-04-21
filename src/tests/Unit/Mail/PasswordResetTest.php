<?php

namespace Tests\Unit\Mail;

use App\Mail\PasswordReset;
use App\User;
use App\VerificationCode;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    /**
     * Test email content
     *
     * @return void
     */
    public function testPasswordResetBuild()
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

        $mail = new PasswordReset($code);
        $html = $mail->build()->render();

        $url = \config('app.url') . '/login/reset/' . $code->short_code . '-' . $code->code;
        $link = "<a href=\"$url\">$url</a>";

        $this->assertSame(\config('app.name') . ' Password Reset', $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, $code->user->name(true)) > 0);
    }
}
