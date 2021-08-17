<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class UserInfo extends Page
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
        $browser->waitFor('@form')
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
            '@form' => '#user-info form',
            '@nav' => 'ul.nav-tabs',
            '@packages' => '#user-packages',
            '@settings' => '#settings',
            '@general' => '#general',
            '@skus' => '#user-skus',
            '@status' => '#status-box',
        ];
    }
}
