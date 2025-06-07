<?php

namespace Tests\Feature\Console\Wallet;

use App\Sku;
use Tests\TestCase;

class AddControllerTest extends TestCase
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
        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $wallet = $owner->wallets()->first();
        $user->assignSku($sku, 1, $wallet);

        // Invalid wallet id
        $code = \Artisan::call("wallet:add-controller 123 {$user->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Invalid user id
        $code = \Artisan::call("wallet:add-controller {$wallet->id} 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // User from another account
        $code = \Artisan::call("wallet:add-controller {$wallet->id} jack@kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User does not belong to this wallet.", $output);

        // Success
        $code = \Artisan::call("wallet:add-controller {$wallet->id} {$user->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertTrue($wallet->fresh()->isController($user));
    }
}
