<?php

namespace Tests\Browser\Reseller;

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

        if (!\config('services.mollie.key')) {
            $this->markTestSkipped('No MOLLIE_KEY');
        }

        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if (\config('services.mollie.key')) {
            $user = $this->getTestUser('reseller@' . \config('app.domain'));
            $wallet = $user->wallets()->first();
            $wallet->payments()->delete();
            $wallet->balance = 0;
            $wallet->save();
        }

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

            $browser->withConfig(['services.payment_provider' => 'mollie'])
                ->visit(new Home())
                ->submitLogon($user->email, \App\Utils::generatePassphrase(), true)
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
                ->on(new WalletPage());
                // Note: This depends on Mollie to Cockcpit communication (webhook)
                // $browser->assertSeeIn('@main .card-title', 'Account balance 12,34 CHF');

            $this->assertSame(1, $wallet->payments()->count());
        });
    }
}
