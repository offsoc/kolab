<?php

namespace Tests\Browser\Pages\Meet;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class RoomList extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/rooms';
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
            ->assertSeeIn('@list .card-title', 'Voice & video conferencing rooms');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@list' => '#rooms-list',
            '@table' => '#rooms-list table',
        ];
    }
}
