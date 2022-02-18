<?php

namespace Tests\Unit\Mail;

use App\Mail\SuspendedDebtor;
use App\User;
use Tests\TestCase;

class SuspendedDebtorTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = new User();

        \config([
                'app.support_url' => 'https://kolab.org/support',
                'app.kb.account_suspended' => 'https://kb.kolab.org/account-suspended',
                'app.kb.account_delete' => 'https://kb.kolab.org/account-delete',
        ]);

        $mail = $this->renderMail(new SuspendedDebtor($user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $deleteUrl = \config('app.kb.account_delete');
        $deleteLink = sprintf('<a href="%s">%s</a>', $deleteUrl, $deleteUrl);
        $moreUrl = \config('app.kb.account_suspended');
        $moreLink = sprintf('<a href="%s">here</a>', $moreUrl);
        $appName = \config('app.name');

        $this->assertSame("$appName Account Suspended", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, $deleteLink) > 0);
        $this->assertTrue(strpos($html, "You have been behind on paying for your $appName account") > 0);
        $this->assertTrue(strpos($html, "over 14 days") > 0);
        $this->assertTrue(strpos($html, "See $moreLink for more information") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, $deleteUrl) > 0);
        $this->assertTrue(strpos($plain, "You have been behind on paying for your $appName account") > 0);
        $this->assertTrue(strpos($plain, "over 14 days") > 0);
        $this->assertTrue(strpos($plain, "See $moreUrl for more information") > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
