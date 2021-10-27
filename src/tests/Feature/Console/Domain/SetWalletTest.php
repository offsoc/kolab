<?php

namespace Tests\Feature\Console\Domain;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SetWalletTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestDomain('domain-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing domain
        $code = \Artisan::call("domain:set-wallet unknown.org 12345");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        $domain = $this->getTestDomain('domain-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);

        // Non-existing wallet
        $code = \Artisan::call("domain:set-wallet domain-delete.com 12345");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Wallet not found.", $output);

        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets->first();

        $code = \Artisan::call("domain:set-wallet domain-delete.com " . $wallet->id);
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('', $output);

        $domain->refresh();
        $sku = \App\Sku::withObjectTenantContext($domain)->where('title', 'domain-hosting')->first();
        $entitlement = $domain->entitlements()->first();

        $this->assertSame($sku->id, $entitlement->sku_id);
        $this->assertSame($wallet->id, $entitlement->wallet_id);

        // Already assigned to a wallet
        $code = \Artisan::call("domain:set-wallet domain-delete.com " . $wallet->id);
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain already assigned to a wallet: {$wallet->id}.", $output);
    }
}
