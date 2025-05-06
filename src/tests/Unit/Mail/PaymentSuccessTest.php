<?php

namespace Tests\Unit\Mail;

use App\Mail\PaymentSuccess;
use App\Payment;
use App\Tenant;
use App\Utils;
use Tests\TestCase;

class PaymentSuccessTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $payment = new Payment();
        $payment->amount = 123;
        $payment->wallet = $user->wallets->first();

        \config(['app.support_url' => 'https://kolab.org/support']);

        $mail = $this->renderMail(new PaymentSuccess($payment, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = Tenant::getConfig($user->tenant_id, 'app.name');

        $this->assertSame("{$appName} Payment Succeeded", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "{$appName} Support") > 0);
        $this->assertTrue(strpos($html, "The auto-payment for your {$user->email} account") > 0);
        $this->assertTrue(strpos($html, "{$appName} Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "{$appName} Support") > 0);
        $this->assertTrue(strpos($plain, "The auto-payment for your {$user->email} account") > 0);
        $this->assertTrue(strpos($plain, "{$appName} Team") > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $payment = new Payment();
        $payment->amount = 123;
        $payment->wallet = $user->wallets->first();

        $appName = Tenant::getConfig($user->tenant_id, 'app.name');

        $mail = new PaymentSuccess($payment, $user);

        $this->assertSame("{$appName} Payment Succeeded", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
