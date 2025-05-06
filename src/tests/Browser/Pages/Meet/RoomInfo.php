<?php

namespace Tests\Browser\Pages\Meet;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class RoomInfo extends Page
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
        $browser->waitUntilMissing('@app .app-loader');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@intro' => '#room-intro',
            '@general' => '#general',
            '@nav' => 'ul.nav-tabs',
            '@settings' => '#settings',
            '@skus' => '#room-skus table',
        ];
    }
}
