<?php

namespace Tests\Browser\Reseller;

use App\Providers\PaymentProvider;
use App\Wallet;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\PaymentMollie;
use Tests\Browser\Pages\Wallet as WalletPage;
use Tests\TestCaseDusk;

class PaymentMollieTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $user = $this->getTestUser('reseller@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->payments()->delete();
        $wallet->balance = 0;
        $wallet->save();

        parent::tearDown();
    }

    /**
     * Test the payment process
     *
     * @group mollie
     */
    public function testPayment(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('reseller@' . \config('app.domain'));
            $wallet = $user->wallets()->first();
            $wallet->payments()->delete();
            $wallet->balance = 0;
            $wallet->save();

            $browser->visit(new Home())
                ->submitLogon($user->email, \App\Utils::generatePassphrase(), true, ['paymentProvider' => 'mollie'])
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@main button', 'Add credit')
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->waitFor('#payment-method-selection .link-creditcard svg')
                        ->waitFor('#payment-method-selection .link-paypal svg')
                        ->waitFor('#payment-method-selection .link-banktransfer svg')
                        ->click('#payment-method-selection .link-creditcard');
                })
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->assertFocused('#amount')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Continue')
                        // Test error handling
                        ->type('@body #amount', 'aaa')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertSeeIn('#amount + span + .invalid-feedback', 'The amount must be a number.')
                        // Submit valid data
                        ->type('@body #amount', '12.34')
                        // Note we use double click to assert it does not create redundant requests
                        ->click('@button-action')
                        ->click('@button-action');
                })
                ->on(new PaymentMollie())
                ->assertSeeIn('@title', $user->tenant->title . ' Payment')
                ->assertSeeIn('@amount', 'CHF 12.34');

            $this->assertSame(1, $wallet->payments()->count());

            // Looks like the Mollie testing mode is limited.
            // We'll select credit card method and mark the payment as paid
            // We can't do much more, we have to trust Mollie their page works ;)

            // For some reason I don't get the method selection form, it
            // immediately jumps to the next step. Let's detect that
            if ($browser->element('@methods')) {
                $browser->click('@methods button.grid-button-creditcard')
                    ->waitFor('button.form__button');
            }

            $browser->click('@status-table input[value="paid"]')
                ->click('button.form__button');

            // Now it should redirect back to wallet page and in background
            // use the webhook to update payment status (and balance).

            // Looks like in test-mode the webhook is executed before redirect
            // so we can expect balance updated on the wallet page

            $browser->waitForLocation('/wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@main .card-title', 'Account balance 12,34 CHF');
        });
    }
}
