<?php

namespace Tests\Unit\Mail;

use App\Jobs\WalletCheck;
use App\Mail\NegativeBalanceDegraded;
use App\User;
use App\Wallet;
use Tests\TestCase;

class NegativeBalanceDegradedTest extends TestCase
{
    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets->first();
        $wallet->balance = -100;
        $wallet->save();

        \config([
                'app.support_url' => 'https://kolab.org/support',
        ]);

        $mail = $this->renderMail(new NegativeBalanceDegraded($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = $user->tenant->title;

        $this->assertSame("$appName Account Degraded", $mail['subject']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "Your {$john->email} account has been degraded") > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "Your {$john->email} account has been degraded") > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }

    /**
     * Test getSubject() and getUser()
     */
    public function testGetSubjectAndUser(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets->first();
        $appName = $user->tenant->title;

        $mail = new NegativeBalanceDegraded($wallet, $user);

        $this->assertSame("$appName Account Degraded", $mail->getSubject());
        $this->assertSame($user, $mail->getUser());
    }
}
