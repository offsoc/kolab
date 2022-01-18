<?php

namespace Tests\Feature\Console\Wallet;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UntilTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command run for a specified wallet
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('wallets-controller@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Non-existing wallet
        $code = \Artisan::call("wallet:until 123");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        // Existing wallet
        $code = \Artisan::call("wallet:until {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Lasts until: unknown", $output);

        $package = \App\Package::withObjectTenantContext($user)->where('title', 'kolab')->first();
        $user->assignPackage($package);
        $wallet->balance = 1000;
        $wallet->save();

        // Existing wallet
        $code = \Artisan::call("wallet:until {$wallet->id}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);

        $expected = \now()->addMonthsWithoutOverflow(1)->addDays(31)->toDateString();
        $this->assertSame("Lasts until: $expected", $output);
    }
}
