<?php

namespace Tests\Browser;

use App\Payment;
use App\Providers\PaymentProvider;
use App\Transaction;
use App\Wallet;
use Carbon\Carbon;
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
                ->assertSeeIn('#wallet .card-title', 'Account balance -12,34 CHF')
                ->assertSeeIn('#wallet .card-title .text-danger', '-12,34 CHF')
                ->assertSeeIn('#wallet .card-text', 'You are out of credit');
        });
    }

    /**
     * Test Receipts tab
     */
    public function testReceipts(): void
    {
        $user = $this->getTestUser('wallets-controller@kolabnow.com', ['password' => 'simple123']);
        $wallet = $user->wallets()->first();
        $wallet->payments()->delete();

        // Log out John and log in the test user
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->waitForLocation('/login')
                ->on(new Home())
                ->submitLogon('wallets-controller@kolabnow.com', 'simple123', true);
        });

        // Assert Receipts tab content when there's no receipts available
        $this->browse(function (Browser $browser) {
            $browser->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('#wallet .card-title', 'Account balance 0,00 CHF')
                ->assertSeeIn('#wallet .card-title .text-success', '0,00 CHF')
                ->assertSeeIn('#wallet .card-text', 'You are in your free trial period.')
                ->assertSeeIn('@nav #tab-receipts', 'Receipts')
                ->with('@receipts-tab', function (Browser $browser) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertSeeIn('p', 'There are no receipts for payments')
                        ->assertDontSeeIn('p', 'Here you can download')
                        ->assertMissing('select')
                        ->assertMissing('button');
                });
        });

        // Create some sample payments
        $receipts = [];
        $date = Carbon::create(intval(date('Y')) - 1, 3, 30);
        $payment = Payment::create([
                'id' => 'AAA1',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Paid in March',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 1111,
        ]);
        $payment->updated_at = $date;
        $payment->save();
        $receipts[] = $date->format('Y-m');

        $date = Carbon::create(intval(date('Y')) - 1, 4, 30);
        $payment = Payment::create([
                'id' => 'AAA2',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Paid in April',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 1111,
        ]);
        $payment->updated_at = $date;
        $payment->save();
        $receipts[] = $date->format('Y-m');

        // Assert Receipts tab with receipts available
        $this->browse(function (Browser $browser) use ($receipts) {
            $browser->refresh()
                ->on(new WalletPage())
                ->assertSeeIn('@nav #tab-receipts', 'Receipts')
                ->with('@receipts-tab', function (Browser $browser) use ($receipts) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertDontSeeIn('p', 'There are no receipts for payments')
                        ->assertSeeIn('p', 'Here you can download')
                        ->assertSeeIn('button', 'Download')
                        ->assertElementsCount('select > option', 2)
                        ->assertSeeIn('select > option:nth-child(1)', $receipts[1])
                        ->assertSeeIn('select > option:nth-child(2)', $receipts[0]);

                    // Download a receipt file
                    $browser->select('select', $receipts[0])
                        ->click('button')
                        ->pause(2000);

                    $files = glob(__DIR__ . '/downloads/*.pdf');

                    $filename = pathinfo($files[0], PATHINFO_BASENAME);
                    $this->assertTrue(strpos($filename, $receipts[0]) !== false);

                    $content = $browser->readDownloadedFile($filename, 0);
                    $this->assertStringStartsWith("%PDF-1.3\n", $content);

                    $browser->removeDownloadedFile($filename);
                });
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
                ->click('@nav #tab-history')
                ->with('@history-tab', function (Browser $browser) use ($pages, $wallet) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertElementsCount('table tbody tr', 10)
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
