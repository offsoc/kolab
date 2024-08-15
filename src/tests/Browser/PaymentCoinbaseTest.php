<?php

namespace Tests\Browser;

use App\Wallet;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Wallet as WalletPage;
use Tests\TestCaseDusk;

class PaymentCoinbaseTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!\config('services.coinbase.key')) {
            $this->markTestSkipped('No COINBASE_KEY');
        }

        $this->deleteTestUser('payment-test@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if (\config('services.coinbase.key')) {
            $this->deleteTestUser('payment-test@kolabnow.com');
        }

        parent::tearDown();
    }

    /**
     * Test the payment process
     *
     * @group coinbase
     */
    public function testPayment(): void
    {
        $user = $this->getTestUser('payment-test@kolabnow.com', [
                'password' => 'simple123',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new Home())
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true)
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->assertSeeIn('@main button', 'Add credit')
                ->click('@main button')
                ->with(new Dialog('@payment-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Top up your wallet')
                        ->waitFor('#payment-method-selection .link-bitcoin svg')
                        ->click('#payment-method-selection .link-bitcoin');
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
                ->waitUntilMissing('@payment-dialog');

            $this->assertSame(1, $user->wallets()->first()->payments()->count());
        });
    }
}
