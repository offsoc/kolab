<?php

namespace Tests\Unit\Mail;

use App\Mail\NegativeBalance;
use App\User;
use App\Tenant;
use App\Wallet;
use Tests\TestCase;

class NegativeBalanceTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets->first();

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
        $appName = Tenant::getConfig($user->tenant_id, 'app.name');

        $this->assertSame("$appName Payment Required", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "your {$user->email} account balance has run into the nega") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "your {$user->email} account balance has run into the nega") > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = new User();
        $wallet = new Wallet();
        $appName = \config('app.name');

        $mail = new NegativeBalance($wallet, $user);

        $this->assertSame("$appName Payment Required", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
