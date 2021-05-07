<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class DistlistList extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/distlists';
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
            ->assertSeeIn('#distlist-list .card-title', 'Distribution lists');
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
            '@table' => '#distlist-list table',
        ];
    }
}
