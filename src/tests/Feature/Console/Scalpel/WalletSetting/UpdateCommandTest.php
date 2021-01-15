<?php

namespace Tests\Feature\Console\Scalpel\WalletSetting;

use Tests\TestCase;

class UpdateCommandTest extends TestCase
{
    public function testHandle(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets->first();
        $wallet->setSetting('test', 'init');

        $this->artisan("scalpel:wallet-setting:update unknown --value=test")
             ->assertExitCode(1)
             ->expectsOutput("No such wallet-setting unknown");

        $setting = $wallet->settings()->where('key', 'test')->first();

        $this->artisan("scalpel:wallet-setting:update {$setting->id} --value=test")
             ->assertExitCode(0);

        $this->assertSame('test', $setting->fresh()->value);
        $this->assertSame('test', $wallet->fresh()->getSetting('test'));
    }
}
