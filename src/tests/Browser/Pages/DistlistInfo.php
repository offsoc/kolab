<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class DistlistInfo extends Page
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
        $browser->waitFor('@general')
            ->waitUntilMissing('.app-loader');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@general' => '#general',
            '@nav' => 'ul.nav-tabs',
            '@settings' => '#settings',
            '@skus' => '#distlist-skus',
            '@status' => '#status-box',
        ];
    }
}
