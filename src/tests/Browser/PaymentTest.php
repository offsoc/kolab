<?php

namespace Tests\Browser;

use App\Wallet;
use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\PaymentMollie;
use Tests\Browser\Pages\Wallet as WalletPage;
use Tests\TestCaseDusk;

class PaymentTest extends TestCaseDusk
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
     * Test a payment process
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
                ->submitLogon('payment-test@kolabnow.com', 'simple123', true)
                ->on(new Dashboard())
                ->click('@links .link-wallet')
                ->on(new WalletPage())
                ->click('@main button')
                ->on(new PaymentMollie())
                ->assertSeeIn('@title', 'Kolab Now Payment')
                ->assertSeeIn('@amount', 'CHF 10.00');

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
                ->assertSeeIn('@main .card-text', 'Current account balance is 10,00 CHF');
        });
    }
}
