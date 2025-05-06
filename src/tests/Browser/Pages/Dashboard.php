<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Dashboard extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url()
    {
        return '/dashboard';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->assertPathIs('/dashboard')
            ->waitUntilMissing('@app .app-loader')
            ->assertPresent('@links');
    }

    /**
     * Assert logged-in user
     *
     * @param Browser $browser The browser object
     * @param string  $user    User email
     */
    public function assertUser($browser, $user)
    {
        $browser->assertVue('$root.authInfo.email', $user, '@dashboard-component');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@app' => '#app',
            '@links' => '#dashboard-nav',
            '@status' => '#status-box',
            '@search' => '#search-box',
        ];
    }
}
