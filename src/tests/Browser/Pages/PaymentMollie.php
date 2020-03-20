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
        $browser->waitFor('#container');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements(): array
    {
        return [
            '@form' => '#container',
            '@title' => '#container .header__info',
            '@amount' => '#container .header__amount',
            '@methods' => '#payment-method-list',
            '@status-table' => 'table.table--select-status',
        ];
    }
}
