<?php

namespace Tests\Unit\Mail;

use App\Mail\PaymentFailure;
use App\Payment;
use App\User;
use Tests\TestCase;

class PaymentFailureTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();
        $payment = new Payment();
        $payment->amount = 123;

        \config(['app.support_url' => 'https://kolab.org/support']);

        $mail = $this->renderMail(new PaymentFailure($payment, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Payment Failed", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "Something went wrong with auto-payment for your $appName account") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "Something went wrong with auto-payment for your $appName account") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = new User();
        $user->id = 1234;
        $payment = new Payment();
        $payment->amount = 123;
        $appName = \config('app.name');

        $mail = new PaymentFailure($payment, $user);

        $this->assertSame("$appName Payment Failed", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
