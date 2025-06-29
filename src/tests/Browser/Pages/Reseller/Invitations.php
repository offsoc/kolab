<?php

namespace Tests\Browser\Pages\Reseller;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Invitations extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/invitations';
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
            ->assertSeeIn('#invitations .card-title', 'Signup invitations');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@create-button' => '.card-text button.create-invite',
            '@create-dialog' => '#invite-create',
            '@search-button' => '#search-form button',
            '@search-input' => '#search-form input',
            '@table' => '#invitations-list',
        ];
    }
}
