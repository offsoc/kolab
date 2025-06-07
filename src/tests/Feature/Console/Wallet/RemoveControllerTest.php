<?php

namespace Tests\Feature\Console\Wallet;

use Tests\TestCase;

class RemoveControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');
        $this->deleteTestUser('wallets-controller-user@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');
        $this->deleteTestUser('wallets-controller-user@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command run for a specified wallet
     */
    public function testHandle(): void
    {
        $owner = $this->getTestUser('wallets-controller@kolabnow.com');
        $user = $this->getTestUser('wallets-controller-user@kolabnow.com');
        $wallet = $owner->wallets()->first();
        $wallet->addController($user);

        // Invalid wallet id
        $code = \Artisan::call("wallet:remove-controller 123 {$user->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Invalid user id
        $code = \Artisan::call("wallet:remove-controller {$wallet->id} 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Account owner is not a controller
        $code = \Artisan::call("wallet:remove-controller {$wallet->id} {$owner->email}");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User is the wallet owner.", $output);

        // User not a controller
        $code = \Artisan::call("wallet:remove-controller {$wallet->id} jack@kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User is not the wallet controller.", $output);

        // Success
        $code = \Artisan::call("wallet:remove-controller {$wallet->id} {$user->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertFalse($wallet->fresh()->isController($user));
    }
}
