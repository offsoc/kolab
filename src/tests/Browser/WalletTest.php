<?php

namespace Tests\Browser;

use App\Wallet;
use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Wallet as WalletPage;
use Tests\TestCaseDusk;

class WalletTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $john = $this->getTestUser('john@kolab.org');
        Wallet::where('user_id', $john->id)->update(['balance' => -1234]);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        Wallet::where('user_id', $john->id)->update(['balance' => 0]);

        parent::tearDown();
    }

    /**
     * Test wallet page (unauthenticated)
     */
    public function testWalletUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/wallet')->on(new Home());
        });
    }

    /**
     * Test wallet "box" on Dashboard
     */
    public function testDashboard(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-wallet .name', 'Wallet')
                ->assertSeeIn('@links .link-wallet .badge', '-12,34 CHF');
        });
    }

    /**
     * Test wallet page
     *
     * @depends testDashboard
     */
    public function testWallet(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('#wallet .card-title', 'Account balance')
                ->assertSeeIn('#wallet .card-text', 'Current account balance is -12,34 CHF');
        });
    }
}
