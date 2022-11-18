<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class PaymentMollie extends Page
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
        $browser->waitFor('form#body table, form#body iframe');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
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
     * @param \Laravel\Dusk\Browser $browser The browser object
     * @param string                $status  Test payment status (paid, open, failed, expired)
     *
     * @return void
     */
    public function submitPayment($browser, $status = 'paid')
    {
        // https://docs.mollie.com/overview/testing
        // https://docs.mollie.com/components/testing

        if ($browser->element('form#body iframe')) {
            $browser->withinFrame('#card-number iframe', function($browser) {
                    $browser->type('#cardNumber', '2223 0000 1047 9399'); // Mastercard
                })
                ->withinFrame('#card-holder-name iframe', function($browser) {
                    $browser->type('#cardHolder', 'Test');
                })
                ->withinFrame('#expiry-date iframe', function($browser) {
                    $browser->type('#expiryDate', '12/' . (date('y') + 1));
                })
                ->withinFrame('#cvv iframe', function($browser) {
                    $browser->type('#verificationCode', '123');
                })
                ->click('#submit-button');
        }

        $browser->waitFor('input[value="' . $status . '"]')
            ->click('input[value="' . $status . '"]')
            ->click('button.form__button');
    }
}
