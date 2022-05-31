<?php

namespace Tests\Unit\Mail;

use App\Mail\TrialEnd;
use App\User;
use Tests\TestCase;

class TrialEndTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();

        \config([
                'app.support_url' => 'https://kolab.org/support',
                'app.kb.payment_system' => 'https://kb.kolab.org/payment-system',
        ]);

        $mail = $this->renderMail(new TrialEnd($user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $paymentUrl = \config('app.kb.payment_system');
        $paymentLink = sprintf('<a href="%s">%s</a>', $paymentUrl, $paymentUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName: Your trial phase has ended", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, $paymentLink) > 0);
        $this->assertTrue(strpos($html, "30 days of free $appName trial") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, $paymentUrl) > 0);
        $this->assertTrue(strpos($plain, "30 days of free $appName trial") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
