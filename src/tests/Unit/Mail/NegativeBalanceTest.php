<?php

namespace Tests\Unit\Mail;

use App\Mail\NegativeBalance;
use App\User;
use Tests\TestCase;

class NegativeBalanceTest extends TestCase
{
    /**
     * Test email content
     *
     * @return void
     */
    public function testBuild()
    {
        $user = new User();

        \config([
                'app.support_url' => 'https://kolab.org/support',
        ]);

        $mail = new NegativeBalance($user);
        $html = $mail->build()->render();

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);

        $appName = \config('app.name');

        $this->assertSame("$appName Payment Reminder", $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "behind on paying for your $appName account") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);
    }
}
