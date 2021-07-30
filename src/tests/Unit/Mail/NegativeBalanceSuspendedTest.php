<?php

namespace Tests\Unit\Mail;

use App\Jobs\WalletCheck;
use App\Mail\NegativeBalanceSuspended;
use App\User;
use App\Wallet;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class NegativeBalanceSuspendedTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * Test email content
     */
    public function testBuild(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $wallet = $user->wallets->first();
        $wallet->balance = -100;
        $wallet->save();

        $threshold = WalletCheck::threshold($wallet, WalletCheck::THRESHOLD_DELETE);

        \config([
                'app.support_url' => 'https://kolab.org/support',
        ]);

        $mail = $this->fakeMail(new NegativeBalanceSuspended($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = $user->tenant->title;

        $this->assertMailSubject("$appName Account Suspended", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "Your $appName account has been suspended") > 0);
        $this->assertTrue(strpos($html, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "Your $appName account has been suspended") > 0);
        $this->assertTrue(strpos($plain, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);
    }
}
