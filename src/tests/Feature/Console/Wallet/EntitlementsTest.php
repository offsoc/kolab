<?php

namespace Tests\Feature\Console\Wallet;

use Tests\TestCase;

class EntitlementsTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("wallet:entitlements unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        $code = \Artisan::call("wallet:entitlements {$wallet->id} --details");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertTrue(str_contains($output, "john@kolab.org (mailbox) 5,00 CHF"));
        $this->assertTrue(str_contains($output, "john@kolab.org (groupware) 4,90 CHF"));
    }
}
