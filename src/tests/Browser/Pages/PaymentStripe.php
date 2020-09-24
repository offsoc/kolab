<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class PaymentStripe extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param \Laravel\Dusk\Browser $browser The browser object
     *
     * @return void
     */
    public function assert($browser)
    {
        $browser->waitFor('.App-Payment');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements(): array
    {
        return [
            '@form' => '.App-Payment > form',
            '@title' => '.App-Overview .ProductSummary',
            '@amount' => '#ProductSummary-totalAmount',
            '@description' => '#ProductSummary-Description',
            '@email-input' => '.App-Payment #email',
            '@cardnumber-input' => '.App-Payment #cardNumber',
            '@cardexpiry-input' => '.App-Payment #cardExpiry',
            '@cardcvc-input' => '.App-Payment #cardCvc',
            '@name-input' => '.App-Payment #billingName',
            '@submit-button' => '.App-Payment form button.SubmitButton',
        ];
    }

    /**
     * Submit payment form.
     *
     * @param \Laravel\Dusk\Browser $browser  The browser object
     *
     * @return void
     */
    public function submitValidCreditCard($browser)
    {
        $browser->type('@name-input', 'Test')
            ->typeSlowly('@cardnumber-input', '4242424242424242', 50)
            ->type('@cardexpiry-input', '12/' . (intval(date('y')) + 1))
            ->type('@cardcvc-input', '123')
            ->press('@submit-button');
    }
}
