<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class PaymentStripe extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->waitFor('.App-Payment');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@form' => '.App-Payment form',
            '@amount' => '.OrderDetails .CurrencyAmount',
            '@description' => '.OrderDetails .LineItem-productName',
            '@email' => '.App-Payment .ReadOnlyFormField-email .ReadOnlyFormField-content',
            '@cardnumber-input' => '.App-Payment #cardNumber',
            '@cardexpiry-input' => '.App-Payment #cardExpiry',
            '@cardcvc-input' => '.App-Payment #cardCvc',
            '@name-input' => '.App-Payment #billingName',
            '@submit-button' => '.App-Payment .ConfirmPayment button',
        ];
    }

    /**
     * Assert payment details.
     *
     * @param Browser $browser The browser object
     */
    public function assertDetails($browser, $description, $amount)
    {
        // Currency selection, choose CHF
        $browser->click('.SideToSideCurrencyToggle-toggle:nth-child(2) button')
            ->pause(1000)
            ->assertSeeIn('@description', $description)
            ->assertSeeIn('@amount', $amount);
    }

    /**
     * Submit payment form.
     *
     * @param Browser $browser The browser object
     */
    public function submitValidCreditCard($browser)
    {
        $browser->type('@name-input', 'Test')
            ->typeSlowly('@cardnumber-input', '4242424242424242', 50)
            ->type('@cardexpiry-input', '12/' . ((int) date('y') + 1))
            ->type('@cardcvc-input', '123')
            ->scrollTo('@submit-button')->pause(200)
            ->press('@submit-button');
    }
}
