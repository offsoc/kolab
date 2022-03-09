<?php

namespace Tests\Unit\Mail;

use App\Mail\PaymentMandateDisabled;
use App\Wallet;
use App\User;
use Tests\TestCase;

class PaymentMandateDisabledTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();
        $wallet = new Wallet();

        \config(['app.support_url' => 'https://kolab.org/support']);

        $mail = $this->renderMail(new PaymentMandateDisabled($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Auto-payment Problem", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "Your $appName account balance") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "Your $appName account balance") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
