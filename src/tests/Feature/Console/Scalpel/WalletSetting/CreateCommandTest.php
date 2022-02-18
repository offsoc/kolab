<?php

namespace Tests\Feature\Console\Scalpel\WalletSetting;

use Tests\TestCase;

class CreateCommandTest extends TestCase
{
    public function testHandle(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets->first();
        $wallet->setSetting('test', null);

        $this->artisan("scalpel:wallet-setting:create --key=test --value=init --wallet_id={$wallet->id}")
             ->assertExitCode(0);

        $setting = $wallet->settings()->where('key', 'test')->first();

        $this->assertSame('init', $setting->value);
        $this->assertSame('init', $wallet->fresh()->getSetting('test'));
    }
}
