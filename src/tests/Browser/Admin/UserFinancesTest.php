<?php

namespace Tests\Browser\Admin;

use App\Discount;
use App\Transaction;
use App\User;
use App\Wallet;
use Carbon\Carbon;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class UserTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->balance = 0;
        $wallet->save();
    }

    /**
     * Test Finances tab (and transactions)
     */
    public function testFinances(): void
    {
        // Assert Jack's Finances tab
        $this->browse(function (Browser $browser) {
            $jack = $this->getTestUser('jack@kolab.org');
            $jack->wallets()->first()->transactions()->delete();
            $page = new UserPage($jack->id);

            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', 'jeroen', true)
                ->on(new Dashboard())
                ->visit($page)
                ->on($page)
                ->assertSeeIn('@nav #tab-finances', 'Finances')
                ->with('@user-finances', function (Browser $browser) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertSeeIn('.card-title:first-child', 'Account balance')
                        ->assertSeeIn('.card-title:first-child .text-success', '0,00 CHF')
                        ->with('form', function (Browser $browser) {
                            $payment_provider = ucfirst(\config('services.payment_provider'));
                            $browser->assertElementsCount('.row', 2)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', 'none')
                                ->assertSeeIn('.row:nth-child(2) label', $payment_provider . ' ID')
                                ->assertVisible('.row:nth-child(2) a');
                        })
                        ->assertSeeIn('h2:nth-of-type(2)', 'Transactions')
                        ->with('table', function (Browser $browser) {
                            $browser->assertMissing('tbody')
                                ->assertSeeIn('tfoot td', "There are no transactions for this account.");
                        })
                        ->assertMissing('table + button');
                });
        });

        // Assert John's Finances tab (with discount, and debit)
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');
            $page = new UserPage($john->id);
            $discount = Discount::where('code', 'TEST')->first();
            $wallet = $john->wallet();
            $wallet->transactions()->delete();
            $wallet->discount()->associate($discount);
            $wallet->debit(2010);
            $wallet->save();

            // Create test transactions
            $transaction = Transaction::create([
                    'user_email' => 'jeroen@jeroen.jeroen',
                    'object_id' => $wallet->id,
                    'object_type' => Wallet::class,
                    'type' => Transaction::WALLET_CREDIT,
                    'amount' => 100,
                    'description' => 'Payment',
            ]);
            $transaction->updated_at = Carbon::now()->previous(Carbon::MONDAY);
            $transaction->save();

            // Click the managed-by link on Jack's page
            $browser->click('@user-info #manager a')
                ->on($page)
                ->with('@user-finances', function (Browser $browser) use ($transaction) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertSeeIn('.card-title:first-child', 'Account balance')
                        ->assertSeeIn('.card-title:first-child .text-danger', '-20,10 CHF')
                        ->with('form', function (Browser $browser) {
                            $browser->assertElementsCount('.row', 2)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', '10% - Test voucher');
                        })
                        ->assertSeeIn('h2:nth-of-type(2)', 'Transactions')
                        ->with('table', function (Browser $browser) use ($transaction) {
                            $browser->assertElementsCount('tbody tr', 2)
                                ->assertMissing('tfoot')
                                ->assertSeeIn('tbody tr:last-child td.email', 'jeroen@jeroen.jeroen');
                        });
                });
        });

        // Now we go to Ned's info page, he's a controller on John's wallet
        $this->browse(function (Browser $browser) {
            $ned = $this->getTestUser('ned@kolab.org');
            $page = new UserPage($ned->id);

            $browser->click('@nav #tab-users')
                ->click('@user-users tbody tr:nth-child(3) td:first-child a')
                ->on($page)
                ->with('@user-finances', function (Browser $browser) {
                    $browser->waitUntilMissing('.app-loader')
                        ->assertSeeIn('.card-title:first-child', 'Account balance')
                        ->assertSeeIn('.card-title:first-child .text-success', '0,00 CHF')
                        ->with('form', function (Browser $browser) {
                            $browser->assertElementsCount('.row', 2)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', 'none');
                        })
                        ->assertSeeIn('h2:nth-of-type(2)', 'Transactions')
                        ->with('table', function (Browser $browser) {
                            $browser->assertMissing('tbody')
                                ->assertSeeIn('tfoot td', "There are no transactions for this account.");
                        })
                        ->assertMissing('table + button');
                });
        });
    }

    /**
     * Test editing wallet discount
     *
     * @depends testFinances
     */
    public function testWalletDiscount(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');

            $browser->visit(new UserPage($john->id))
                ->pause(100)
                ->waitUntilMissing('@user-finances .app-loader')
                ->click('@user-finances #discount button')
                // Test dialog content, and closing it with Cancel button
                ->with(new Dialog('#discount-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Account discount')
                        ->assertFocused('@body select')
                        ->assertSelected('@body select', '')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Submit')
                        ->click('@button-cancel');
                })
                ->assertMissing('#discount-dialog')
                ->click('@user-finances #discount button')
                // Change the discount
                ->with(new Dialog('#discount-dialog'), function (Browser $browser) {
                    $browser->click('@body select')
                        ->click('@body select option:nth-child(2)')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User wallet updated successfully.')
                ->assertSeeIn('#discount span', '10% - Test voucher')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '3,99 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,99 CHF/month¹')
                        ->assertSeeIn('table + .hint', '¹ applied discount: 10% - Test voucher');
                })
                // Change back to 'none'
                ->click('@nav #tab-finances')
                ->click('@user-finances #discount button')
                ->with(new Dialog('#discount-dialog'), function (Browser $browser) {
                    $browser->click('@body select')
                        ->click('@body select option:nth-child(1)')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User wallet updated successfully.')
                ->assertSeeIn('#discount span', 'none')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '4,44 CHF/month')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF/month')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '5,55 CHF/month')
                        ->assertMissing('table + .hint');
                });
        });
    }

    /**
     * Test awarding/penalizing a wallet
     *
     * @depends testFinances
     */
    public function testBonusPenalty(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');

            $browser->visit(new UserPage($john->id))
                ->waitFor('@user-finances #button-award')
                ->click('@user-finances #button-award')
                // Test dialog content, and closing it with Cancel button
                ->with(new Dialog('#oneoff-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Add a bonus to the wallet')
                        ->assertFocused('@body input#oneoff_amount')
                        ->assertSeeIn('@body label[for="oneoff_amount"]', 'Amount')
                        ->assertvalue('@body input#oneoff_amount', '')
                        ->assertSeeIn('@body label[for="oneoff_description"]', 'Description')
                        ->assertvalue('@body input#oneoff_description', '')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Submit')
                        ->click('@button-cancel');
                })
                ->assertMissing('#oneoff-dialog');

            // Test bonus
            $browser->click('@user-finances #button-award')
                ->with(new Dialog('#oneoff-dialog'), function (Browser $browser) {
                    // Test input validation for a bonus
                    $browser->type('@body #oneoff_amount', 'aaa')
                        ->type('@body #oneoff_description', '')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertVisible('@body #oneoff_amount.is-invalid')
                        ->assertVisible('@body #oneoff_description.is-invalid')
                        ->assertSeeIn(
                            '@body #oneoff_amount + span + .invalid-feedback',
                            'The amount must be a number.'
                        )
                        ->assertSeeIn(
                            '@body #oneoff_description + .invalid-feedback',
                            'The description field is required.'
                        );

                    // Test adding a bonus
                    $browser->type('@body #oneoff_amount', '12.34')
                        ->type('@body #oneoff_description', 'Test bonus')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_SUCCESS, 'The bonus has been added to the wallet successfully.');
                })
                ->assertMissing('#oneoff-dialog')
                ->assertSeeIn('@user-finances .card-title span.text-success', '12,34 CHF')
                ->waitUntilMissing('.app-loader')
                ->with('table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 3)
                        ->assertMissing('tfoot')
                        ->assertSeeIn('tbody tr:first-child td.description', 'Bonus: Test bonus')
                        ->assertSeeIn('tbody tr:first-child td.email', 'jeroen@jeroen.jeroen')
                        ->assertSeeIn('tbody tr:first-child td.price', '12,34 CHF');
                });

            $this->assertSame(1234, $john->wallets()->first()->balance);

            // Test penalty
            $browser->click('@user-finances #button-penalty')
                // Test dialog content, and closing it with Cancel button
                ->with(new Dialog('#oneoff-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Add a penalty to the wallet')
                        ->assertFocused('@body input#oneoff_amount')
                        ->assertSeeIn('@body label[for="oneoff_amount"]', 'Amount')
                        ->assertvalue('@body input#oneoff_amount', '')
                        ->assertSeeIn('@body label[for="oneoff_description"]', 'Description')
                        ->assertvalue('@body input#oneoff_description', '')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Submit')
                        ->click('@button-cancel');
                })
                ->assertMissing('#oneoff-dialog')
                ->click('@user-finances #button-penalty')
                ->with(new Dialog('#oneoff-dialog'), function (Browser $browser) {
                    // Test input validation for a penalty
                    $browser->type('@body #oneoff_amount', '')
                        ->type('@body #oneoff_description', '')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertVisible('@body #oneoff_amount.is-invalid')
                        ->assertVisible('@body #oneoff_description.is-invalid')
                        ->assertSeeIn(
                            '@body #oneoff_amount + span + .invalid-feedback',
                            'The amount field is required.'
                        )
                        ->assertSeeIn(
                            '@body #oneoff_description + .invalid-feedback',
                            'The description field is required.'
                        );

                    // Test adding a penalty
                    $browser->type('@body #oneoff_amount', '12.35')
                        ->type('@body #oneoff_description', 'Test penalty')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_SUCCESS, 'The penalty has been added to the wallet successfully.');
                })
                ->assertMissing('#oneoff-dialog')
                ->assertSeeIn('@user-finances .card-title span.text-danger', '-0,01 CHF');

            $this->assertSame(-1, $john->wallets()->first()->balance);
        });
    }
}
