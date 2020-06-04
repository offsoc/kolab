<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class Wallet extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/wallet';
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
        $browser->assertPathIs($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->assertSeeIn('#wallet .card-title', 'Account balance');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@main' => '#wallet',
            '@payment-dialog' => '#payment-dialog',
            '@nav' => 'ul.nav-tabs',
            '@history-tab' => '#wallet-history',
        ];
    }
}
