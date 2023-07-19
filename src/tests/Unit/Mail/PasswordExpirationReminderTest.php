<?php

namespace Tests\Unit\Mail;

use App\Mail\PasswordExpirationReminder;
use App\User;
use App\Utils;
use Tests\TestCase;

class PasswordExpirationReminderTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User([
                'name' => 'User Name',
        ]);

        $expiresOn = now()->copy()->addDays(7)->toDateString();

        $mail = $this->renderMail(new PasswordExpirationReminder($user, $expiresOn));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $url = Utils::serviceUrl('profile');
        $link = "<a href=\"$url\">$url</a>";
        $appName = \config('app.name');

        $this->assertSame("$appName password expires on $expiresOn", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $link) > 0);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $expiresOn) > 0);

        $this->assertStringStartsWith("Dear " . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $link) > 0);
        $this->assertTrue(strpos($plain, $expiresOn) > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = new User(['name' => 'User Name']);
        $appName = \config('app.name');
        $expiresOn = now()->copy()->addDays(7)->toDateString();

        $mail = new PasswordExpirationReminder($user, $expiresOn);

        $this->assertSame("$appName password expires on $expiresOn", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
