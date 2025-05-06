<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Wallet extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/wallet';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->assertPathIs($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->assertSeeIn('#wallet .card-title', 'Account balance');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@main' => '#wallet',
            '@payment-dialog' => '#payment-dialog',
            '@nav' => 'ul.nav-tabs',
            '@history-tab' => '#history',
            '@receipts-tab' => '#receipts',
            '@refprograms-tab' => '#refprograms',
            '@payments-tab' => '#payments',
        ];
    }
}
