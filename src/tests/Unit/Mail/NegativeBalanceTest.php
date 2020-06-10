<?php

namespace Tests\Unit\Mail;

use App\Mail\NegativeBalance;
use App\User;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class NegativeBalanceTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();

        \config([
                'app.support_url' => 'https://kolab.org/support',
        ]);

        $mail = $this->fakeMail(new NegativeBalance($user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertMailSubject("$appName Payment Reminder", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "behind on paying for your $appName account") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "behind on paying for your $appName account") > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
