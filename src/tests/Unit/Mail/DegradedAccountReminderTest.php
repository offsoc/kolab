<?php

namespace Tests\Unit\Mail;

use App\Mail\DegradedAccountReminder;
use App\User;
use App\Wallet;
use Tests\TestCase;

class DegradedAccountReminderTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets->first();

        $mail = $this->renderMail(new DegradedAccountReminder($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $dashboardUrl = \App\Utils::serviceUrl('/dashboard');
        $dashboardLink = sprintf('<a href="%s">%s</a>', $dashboardUrl, $dashboardUrl);
        $appName = $user->tenant->title;

        $this->assertSame("$appName Reminder: Your account is free", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $dashboardLink) > 0);
        $this->assertTrue(strpos($html, "your account is a free account") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $dashboardUrl) > 0);
        $this->assertTrue(strpos($plain, "your account is a free account") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets->first();
        $appName = $user->tenant->title;

        $mail = new DegradedAccountReminder($wallet, $user);

        $this->assertSame("$appName Reminder: Your account is free", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
