<?php

namespace Tests\Unit\Mail;

use App\Mail\SignupInvitation;
use App\SignupInvitation as SI;
use App\Utils;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class SignupInvitationTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $invitation = new SI([
                'id' => 'abc',
                'email' => 'test@email',
        ]);

        $mail = $this->fakeMail(new SignupInvitation($invitation));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $url = Utils::serviceUrl('/signup/invite/' . $invitation->id);
        $link = "<a href=\"$url\">$url</a>";
        $appName = \config('app.name');

        $this->assertMailSubject("$appName Invitation", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, "invited to join $appName") > 0);

        $this->assertStringStartsWith("Hi,", $plain);
        $this->assertTrue(strpos($plain, "invited to join $appName") > 0);
        $this->assertTrue(strpos($plain, $url) > 0);
    }
}
