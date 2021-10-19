<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class WalletsTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:wallets unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();

        $code = \Artisan::call("user:wallets john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame(trim("{$wallet->id} {$wallet->description}"), $output);
    }
}
