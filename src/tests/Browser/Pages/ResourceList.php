<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class ResourceList extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/resources';
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
            ->assertSeeIn('#resource-list .card-title', 'Resources');
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
            '@table' => '#resource-list table',
        ];
    }
}
