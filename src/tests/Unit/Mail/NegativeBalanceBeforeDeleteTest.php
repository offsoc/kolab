<?php

namespace Tests\Unit\Mail;

use App\Jobs\WalletCheck;
use App\Mail\NegativeBalanceBeforeDelete;
use App\User;
use App\Wallet;
use Tests\MailInterceptTrait;
use Tests\TestCase;

class NegativeBalanceBeforeDeleteTest extends TestCase
{
    use MailInterceptTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        \App\TenantSetting::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \App\TenantSetting::truncate();

        parent::tearDown();
    }

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

        $mail = $this->fakeMail(new NegativeBalanceBeforeDelete($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = \App\Utils::serviceUrl('/wallet');
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = \config('app.support_url');
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);
        $appName = \config('app.name');

        $this->assertMailSubject("$appName Final Warning", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "This is a final reminder to settle your $appName") > 0);
        $this->assertTrue(strpos($html, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($html, "$appName Support") > 0);
        $this->assertTrue(strpos($html, "$appName Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "This is a final reminder to settle your $appName") > 0);
        $this->assertTrue(strpos($plain, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($plain, "$appName Support") > 0);
        $this->assertTrue(strpos($plain, "$appName Team") > 0);

        // Test with user that is not the same tenant as in .env
        $user = $this->getTestUser('user@sample-tenant.dev-local');
        $tenant = $user->tenant;
        $wallet = $user->wallets->first();
        $wallet->balance = -100;
        $wallet->save();

        $threshold = WalletCheck::threshold($wallet, WalletCheck::THRESHOLD_DELETE);

        $tenant->setSettings([
                'app.support_url' => 'https://test.org/support',
                'app.public_url' => 'https://test.org',
        ]);

        $mail = $this->fakeMail(new NegativeBalanceBeforeDelete($wallet, $user));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $walletUrl = 'https://test.org/wallet';
        $walletLink = sprintf('<a href="%s">%s</a>', $walletUrl, $walletUrl);
        $supportUrl = 'https://test.org/support';
        $supportLink = sprintf('<a href="%s">%s</a>', $supportUrl, $supportUrl);

        $this->assertMailSubject("{$tenant->title} Final Warning", $mail['message']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertTrue(strpos($html, $user->name(true)) > 0);
        $this->assertTrue(strpos($html, $walletLink) > 0);
        $this->assertTrue(strpos($html, $supportLink) > 0);
        $this->assertTrue(strpos($html, "This is a final reminder to settle your {$tenant->title}") > 0);
        $this->assertTrue(strpos($html, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($html, "{$tenant->title} Support") > 0);
        $this->assertTrue(strpos($html, "{$tenant->title} Team") > 0);

        $this->assertStringStartsWith('Dear ' . $user->name(true), $plain);
        $this->assertTrue(strpos($plain, $walletUrl) > 0);
        $this->assertTrue(strpos($plain, $supportUrl) > 0);
        $this->assertTrue(strpos($plain, "This is a final reminder to settle your {$tenant->title}") > 0);
        $this->assertTrue(strpos($plain, $threshold->toDateString()) > 0);
        $this->assertTrue(strpos($plain, "{$tenant->title} Support") > 0);
        $this->assertTrue(strpos($plain, "{$tenant->title} Team") > 0);
    }
}
