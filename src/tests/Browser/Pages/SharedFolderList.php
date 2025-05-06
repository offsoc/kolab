<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class SharedFolderList extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/shared-folders';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->assertPathIs($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->assertSeeIn('#folder-list .card-title', 'Shared folders');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@table' => '#folder-list table',
        ];
    }
}
