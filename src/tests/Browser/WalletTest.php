<?php

namespace Tests\Browser;

use App\Transaction;
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

        $this->deleteTestUser('wallets-controller@kolabnow.com');

        $john = $this->getTestUser('john@kolab.org');
        Wallet::where('user_id', $john->id)->update(['balance' => -1234]);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');

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

    /**
     * Test History tab
     */
    public function testHistory(): void
    {
        $user = $this->getTestUser('wallets-controller@kolabnow.com', ['password' => 'simple123']);

        // Log out John and log in the test user
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->waitForLocation('/login')
                ->on(new Home())
                ->submitLogon('wallets-controller@kolabnow.com', 'simple123', true);
        });

        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $user->assignPackage($package_kolab);
        $wallet = $user->wallets()->first();

        // Create some sample transactions
        $transactions = $this->createTestTransactions($wallet);
        $transactions = array_reverse($transactions);
        $pages = array_chunk($transactions, 10 /* page size*/);

        $this->browse(function (Browser $browser) use ($pages, $wallet) {
            $browser->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@nav #tab-history', 'History')
                ->with('@history-tab', function (Browser $browser) use ($pages, $wallet) {
                    $browser->assertElementsCount('table tbody tr', 10)
                        ->assertMissing('table td.email')
                        ->assertSeeIn('#transactions-loader button', 'Load more');

                    foreach ($pages[0] as $idx => $transaction) {
                        $selector = 'table tbody tr:nth-child(' . ($idx + 1) . ')';
                        $priceStyle = $transaction->type == Transaction::WALLET_AWARD ? 'text-success' : 'text-danger';
                        $browser->assertSeeIn("$selector td.description", $transaction->shortDescription())
                            ->assertMissing("$selector td.selection button")
                            ->assertVisible("$selector td.price.{$priceStyle}");
                        // TODO: Test more transaction details
                    }

                    // Load the next page
                    $browser->click('#transactions-loader button')
                        ->waitUntilMissing('.app-loader')
                        ->assertElementsCount('table tbody tr', 12)
                        ->assertMissing('#transactions-loader button');

                    $debitEntry = null;
                    foreach ($pages[1] as $idx => $transaction) {
                        $selector = 'table tbody tr:nth-child(' . ($idx + 1 + 10) . ')';
                        $priceStyle = $transaction->type == Transaction::WALLET_CREDIT ? 'text-success' : 'text-danger';
                        $browser->assertSeeIn("$selector td.description", $transaction->shortDescription());

                        if ($transaction->type == Transaction::WALLET_DEBIT) {
                            $debitEntry = $selector;
                        } else {
                            $browser->assertMissing("$selector td.selection button");
                        }
                    }

                    // Load sub-transactions
                    $browser->click("$debitEntry td.selection button")
                        ->waitUntilMissing('.app-loader')
                        ->assertElementsCount("$debitEntry td.description ul li", 2)
                        ->assertMissing("$debitEntry td.selection button");
                });
        });
    }
}
