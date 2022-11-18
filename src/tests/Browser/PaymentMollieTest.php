<?php

namespace Tests\Browser;

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

        $this->deleteTestUser('payment-test@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('payment-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test the payment process
     *
     * @group mollie
     */
    public function testPayment(): void
    {
        $user = $this->getTestUser('payment-test@kolabnow.com', [
                'password' => 'simple123',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new Home())
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true, ['paymentProvider' => 'mollie'])
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
                ->assertSeeIn('@amount', 'CHF 12.34')
                ->submitPayment()
                ->waitForLocation('/wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@main .card-title', 'Account balance 12,34 CHF');

            $this->assertSame(1, $user->wallets()->first()->payments()->count());
        });
    }

    /**
     * Test the auto-payment setup process
     *
     * @group mollie
     */
    public function testAutoPaymentSetup(): void
    {
        $user = $this->getTestUser('payment-test@kolabnow.com', [
                'password' => 'simple123',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new Home())
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true, ['paymentProvider' => 'mollie'])
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertMissing('@body #mandate-form .alert')
                ->click('@main #mandate-form button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->waitFor('#payment-method-selection .link-creditcard svg')
                        ->assertMissing('#payment-method-selection .link-paypal')
                        ->assertMissing('#payment-method-selection .link-banktransfer')
                        ->click('#payment-method-selection .link-creditcard');
                })
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->assertSeeIn('@body label[for="mandate_amount"]', 'Fill up by')
                        ->assertValue('@body #mandate_amount', PaymentProvider::MIN_AMOUNT / 100)
                        ->assertSeeIn('@body label[for="mandate_balance"]', 'when account balance is below') // phpcs:ignore
                        ->assertValue('@body #mandate_balance', '0')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Continue')
                        // Test error handling
                        ->type('@body #mandate_amount', 'aaa')
                        ->type('@body #mandate_balance', '-1')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertVisible('@body #mandate_amount.is-invalid')
                        ->assertVisible('@body #mandate_balance.is-invalid')
                        ->assertSeeIn('#mandate_amount + span + .invalid-feedback', 'The amount must be a number.')
                        ->assertSeeIn('#mandate_balance + span + .invalid-feedback', 'The balance must be at least 0.')
                        ->type('@body #mandate_amount', 'aaa')
                        ->type('@body #mandate_balance', '0')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertVisible('@body #mandate_amount.is-invalid')
                        ->assertMissing('@body #mandate_balance.is-invalid')
                        ->assertSeeIn('#mandate_amount + span + .invalid-feedback', 'The amount must be a number.')
                        ->assertMissing('#mandate_balance + span + .invalid-feedback')
                        // Submit valid data
                        ->type('@body #mandate_amount', '100')
                        ->type('@body #mandate_balance', '0')
                        // Note we use double click to assert it does not create redundant requests
                        ->click('@button-action')
                        ->click('@button-action');
                })
                ->on(new PaymentMollie())
                ->assertSeeIn('@title', $user->tenant->title . ' Auto-Payment Setup')
                ->assertMissing('@amount')
                ->submitPayment()
                ->waitForLocation('/wallet')
                ->visit('/wallet?paymentProvider=mollie')
                ->waitFor('#mandate-info')
                ->assertPresent('#mandate-info p:first-child')
                ->assertSeeIn(
                    '#mandate-info p:first-child',
                    'Auto-payment is set to fill up your account by 100 CHF ' .
                    'every time your account balance gets under 0 CHF.'
                )
                ->assertSeeIn(
                    '#mandate-info p:nth-child(2)',
                    'Mastercard (**** **** **** 9399)'
                )
                ->assertMissing('@body .alert');

            $this->assertSame(1, $user->wallets()->first()->payments()->count());
        });

        // Test updating (disabled) auto-payment
        $this->browse(function (Browser $browser) use ($user) {
            $wallet = $user->wallets()->first();
            $wallet->setSetting('mandate_disabled', 1);

            $browser->refresh()
                ->on(new WalletPage())
                ->waitFor('#mandate-info')
                ->assertSeeIn(
                    '#mandate-info .disabled-mandate',
                    'The configured auto-payment has been disabled'
                )
                ->assertSeeIn('#mandate-info button.btn-primary', 'Change auto-payment')
                ->click('#mandate-info button.btn-primary')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Update auto-payment')
                    ->assertSeeIn(
                        '@body form .disabled-mandate',
                        'The auto-payment is disabled.'
                    )
                    ->assertValue('@body #mandate_amount', '100')
                    ->assertValue('@body #mandate_balance', '0')
                    ->assertSeeIn('@button-cancel', 'Cancel')
                    ->assertSeeIn('@button-action', 'Submit')
                    // Test error handling
                    ->type('@body #mandate_amount', 'aaa')
                    ->click('@button-action')
                    ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                    ->assertVisible('@body #mandate_amount.is-invalid')
                    ->assertSeeIn('#mandate_amount + span + .invalid-feedback', 'The amount must be a number.')
                    // Submit valid data
                    ->type('@body #mandate_amount', '50')
                    ->click('@button-action');
                })
                ->waitUntilMissing('#payment-dialog')
                ->assertToast(Toast::TYPE_SUCCESS, 'The auto-payment has been updated.')
                // make sure the "disabled" text isn't there
                ->assertMissing('#mandate-info .disabled-mandate')
                ->click('#mandate-info button.btn-primary')
                ->assertMissing('form .disabled-mandate')
                ->click('button.modal-cancel');
        });

        // Test deleting auto-payment
        $this->browse(function (Browser $browser) {
            $browser->on(new WalletPage())
            ->waitFor('#mandate-info')
            ->assertSeeIn('#mandate-info * button.btn-danger', 'Cancel auto-payment')
            ->assertVisible('#mandate-info * button.btn-danger')
            ->click('#mandate-info * button.btn-danger')
            ->assertToast(Toast::TYPE_SUCCESS, 'The auto-payment has been removed.')
            ->assertVisible('#mandate-form')
            ->assertMissing('#mandate-info');
        });

        // Test pending and failed mandate
        $this->browse(function (Browser $browser) {
            $browser->on(new WalletPage())
                ->assertMissing('@body #mandate-form .alert')
                ->click('@main #mandate-form button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->waitFor('#payment-method-selection .link-creditcard')
                        ->click('#payment-method-selection .link-creditcard');
                })
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Continue')
                        // Submit valid data
                        ->type('@body #mandate_amount', '100')
                        ->type('@body #mandate_balance', '0')
                        ->click('@button-action');
                })
                ->on(new PaymentMollie())
                ->submitPayment('open')
                ->waitForLocation('/wallet')
                ->visit('/wallet?paymentProvider=mollie')
                ->on(new WalletPage())
                ->assertSeeIn(
                    '#mandate-info .alert-warning',
                    'The setup of the automatic payment is still in progress.'
                )
                // Delete the mandate
                ->click('#mandate-info * button.btn-danger')
                ->assertToast(Toast::TYPE_SUCCESS, 'The auto-payment has been removed.')
                ->assertMissing('@body #mandate-form .alert')

                // Create a new mandate
                ->click('@main #mandate-form button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->waitFor('#payment-method-selection .link-creditcard')
                        ->click('#payment-method-selection .link-creditcard');
                })
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Set up auto-payment')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Continue')
                        // Submit valid data
                        ->type('@body #mandate_amount', '100')
                        ->type('@body #mandate_balance', '0')
                        ->click('@button-action');
                })
                ->on(new PaymentMollie())
                ->submitPayment('failed')
                ->waitForLocation('/wallet')
                ->visit('/wallet?paymentProvider=mollie')
                ->on(new WalletPage())
                ->waitFor('#mandate-form .alert-danger')
                ->assertSeeIn(
                    '#mandate-form .alert-danger',
                    'The setup of automatic payments failed. Restart the process to enable'
                )
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->waitFor('#mandate-form')
                        ->assertMissing('#mandate-info');
                });
        });
    }
}
