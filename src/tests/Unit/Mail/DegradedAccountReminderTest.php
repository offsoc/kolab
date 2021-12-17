<?php

namespace Tests\Unit\Mail;

use App\Mail\DegradedAccountReminder;
use App\User;
use App\Wallet;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class DegradedAccountReminderTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets->first();

        $mail = $this->fakeMail(new DegradedAccountReminder($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $dashboardUrl = \App\Utils::serviceUrl('/dashboard');
        $dashboardLink = sprintf('<a href="%s">%s</a>', $dashboardUrl, $dashboardUrl);
        $appName = $user->tenant->title;

        $this->assertMailSubject("$appName Reminder: Your account is free", $mail['message']);

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
}
