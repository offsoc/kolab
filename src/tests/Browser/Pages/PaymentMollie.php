<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class PaymentMollie extends Page
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
        $browser->waitFor('form#body')->waitFor('@title');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@title' => '#container .header__info',
            '@amount' => '#container .header__amount',
        ];
    }

    /**
     * Submit payment form.
     *
     * @param Browser $browser The browser object
     * @param string  $status  Test payment status (paid, open, failed, expired)
     */
    public function submitPayment($browser, $status = 'paid')
    {
        // https://docs.mollie.com/overview/testing
        // https://docs.mollie.com/components/testing
        $browser
            ->withinFrame('form#body iframe', static function ($browser) {
                $browser->waitFor('#cardNumber')->type('#cardNumber', '2223 0000 1047 9399'); // Mastercard
                $browser->waitFor('#cardHolder')->type('#cardHolder', 'Test');
                $browser->waitFor('#cardExpiryDate')->type('#cardExpiryDate', '12/' . (date('y') + 1));
                $browser->waitFor('#cardCvv')->type('#cardCvv', '123')
                    ->click('button[type=submit]');
            })
            ->waitFor('input[value="' . $status . '"]')
            ->click('input[value="' . $status . '"]')
            ->click('button.form__button');
    }
}
