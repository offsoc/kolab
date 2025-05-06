<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class UserInfo extends Page
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
        $browser->waitFor('@general form')
            ->waitUntilMissing('.app-loader');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@nav' => 'ul.nav-tabs',
            '@packages' => '#user-packages',
            '@settings' => '#settings',
            '@setting-options' => '#options .accordion-body',
            '@setting-options-head' => '#options-header',
            '@setting-delegation' => '#delegation .accordion-body',
            '@setting-delegation-head' => '#delegation-header',
            '@general' => '#general',
            '@personal' => '#personal',
            '@skus' => '#user-skus',
            '@status' => '#status-box',
        ];
    }
}
