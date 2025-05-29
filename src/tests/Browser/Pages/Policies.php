<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Policies extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/policies';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->waitFor('@password-form')
            ->waitUntilMissing('.app-loader');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@password-form' => '#password form',
            '@maildelivery-form' => '#mailDelivery form',
        ];
    }
}
