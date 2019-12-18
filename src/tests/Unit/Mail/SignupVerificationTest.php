<?php

namespace Tests\Unit\Mail;

use App\Mail\SignupVerification;
use App\SignupCode;

use Tests\TestCase;

class SignupVerificationTest extends TestCase
{
    /**
     * Test email content
     *
     * @return void
     */
    public function testSignupVerificationBuild()
    {
        $code = new SignupCode([
                'code' => 'code',
                'short_code' => 'short-code',
                'data' => [
                    'email' => 'test@email',
                    'name' => 'Test Name',
                ],
        ]);

        $mail = new SignupVerification($code);
        $html = $mail->build()->render();

        $url = \config('app.url') . '/signup/' . $code->short_code . '-' . $code->code;
        $link = "<a src=\"$url\">$url</a>";

        $this->assertSame(\config('app.name') . ' Registration', $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, $code->data['name']) > 0);
    }
}
