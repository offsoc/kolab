<?php

namespace Tests\Unit\Mail;

use App\Mail\SignupVerification;
use App\SignupCode;
use App\Utils;
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
                    'first_name' => 'First',
                    'last_name' => 'Last',
                ],
        ]);

        $mail = new SignupVerification($code);
        $html = $mail->build()->render();

        $url = Utils::serviceUrl('/signup/' . $code->short_code . '-' . $code->code);
        $link = "<a href=\"$url\">$url</a>";

        $this->assertSame(\config('app.name') . ' Registration', $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, 'First Last') > 0);
    }
}
