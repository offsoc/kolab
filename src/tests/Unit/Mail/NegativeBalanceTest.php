<?php

namespace Tests\Unit\Mail;

use App\Mail\NegativeBalance;
use App\User;
use App\Wallet;
use Tests\TestCase;

class NegativeBalanceTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();
        $wallet = new Wallet();

        \config([
                'app.support_url' => 'https://kolab.org/support',
        ]);

        $mail = $this->renderMail(new NegativeBalance($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Payment Required", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "your $appName account balance has run into the nega") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "your $appName account balance has run into the nega") > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
