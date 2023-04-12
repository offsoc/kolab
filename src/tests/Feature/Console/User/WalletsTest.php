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
        $this->assertSame("No such user unknown", $output);

        $user = $this->getTestUser('john@kolab.org');
        $wallet = $user->wallets()->first();
        $wallet->balance = -100;
        $wallet->save();

        $code = \Artisan::call("user:wallets john@kolab.org --attr=balance --attr=user_id");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$wallet->id} {$wallet->balance} {$user->id}", $output);
    }
}
