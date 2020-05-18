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

        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true, ['paymentProvider' => 'mollie'])
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@main button', 'Add credit')
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->assertFocused('#amount')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@body #payment-form button', 'Continue')
                        // Test error handling
                        ->type('@body #amount', 'aaa')
                        ->click('@body #payment-form button')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertSeeIn('#amount + span + .invalid-feedback', 'The amount must be a number.')
                        // Submit valid data
                        ->type('@body #amount', '12.34')
                        ->click('@body #payment-form button');
                })
                ->on(new PaymentMollie())
                ->assertSeeIn('@title', \config('app.name') . ' Payment')
                ->assertSeeIn('@amount', 'CHF 12.34');

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
                ->assertSeeIn('@main .card-text', 'Current account balance is 12,34 CHF');
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

        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true, ['paymentProvider' => 'mollie'])
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@body #mandate-form button', 'Set up auto-payment')
                        ->click('@body #mandate-form button')
                        ->assertSeeIn('@title', 'Add auto-payment')
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
                        ->click('@button-action');
                })
                ->on(new PaymentMollie())
                ->assertSeeIn('@title', \config('app.name') . ' Auto-Payment Setup')
                ->assertMissing('@amount')
                ->submitValidCreditCard()
                ->waitForLocation('/wallet')
                ->visit('/wallet?paymentProvider=mollie')
                ->on(new WalletPage())
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $expected = 'Auto-payment is set to fill up your account by 100 CHF every'
                        . ' time your account balance gets under 0 CHF. You will be charged'
                        . ' via Mastercard (**** **** **** 6787).';

                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->waitFor('#mandate-info')
                        ->assertSeeIn('#mandate-info p:first-child', $expected)
                        ->click('@button-cancel');
                });
        });

        // Test updating auto-payment
        $this->browse(function (Browser $browser) use ($user) {
            $browser->on(new WalletPage())
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@body #mandate-info button.btn-primary', 'Change auto-payment')
                        ->click('@body #mandate-info button.btn-primary')
                        ->assertSeeIn('@title', 'Update auto-payment')
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
                ->assertToast(Toast::TYPE_SUCCESS, 'The auto-payment has been updated.');
        });

        // Test deleting auto-payment
        $this->browse(function (Browser $browser) use ($user) {
            $browser->on(new WalletPage())
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@body #mandate-info button.btn-danger', 'Cancel auto-payment')
                        ->click('@body #mandate-info button.btn-danger')
                        ->assertToast(Toast::TYPE_SUCCESS, 'The auto-payment has been removed.')
                        ->assertVisible('@body #mandate-form')
                        ->assertMissing('@body #mandate-info');
                });
        });
    }
}
