<?php

namespace Tests\Unit\Mail;

use App\Mail\PaymentSuccess;
use App\Payment;
use App\User;
use Tests\TestCase;

class PaymentSuccessTest extends TestCase
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
        $payment = new Payment();
        $payment->amount = 123;

        \config(['app.support_url' => 'https://kolab.org/support']);

        $mail = new PaymentSuccess($payment, $user);
        $html = $mail->build()->render();

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Payment Succeeded", $mail->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "The auto-payment for your $appName account") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);
    }
}
