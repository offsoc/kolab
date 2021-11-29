<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class ResourceInfo extends Page
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
        $browser->waitFor('@general')
            ->waitUntilMissing('.app-loader');
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
            '@general' => '#general',
            '@nav' => 'ul.nav-tabs',
            '@settings' => '#settings',
            '@status' => '#status-box',
        ];
    }
}
