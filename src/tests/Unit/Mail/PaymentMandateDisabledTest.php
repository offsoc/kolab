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
     *
     * @return void
     */
    public function testBuild()
    {
        // @phpstan-ignore-next-line
        $user = new User();
        $wallet = new Wallet();

        \config(['app.support_url' => 'https://kolab.org/support']);

        $mail = new PaymentMandateDisabled($wallet, $user);
        $html = $mail->build()->render();

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Auto-payment Problem", $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "Your $appName account balance") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);
    }
}
