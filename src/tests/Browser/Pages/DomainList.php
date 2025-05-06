<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class DomainList extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/domains';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->waitForLocation($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->assertSeeIn('@list .card-title', 'Domains');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@list' => '#domain-list',
            '@table' => '#domain-list table',
        ];
    }
}
