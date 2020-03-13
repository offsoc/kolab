<?php

namespace Tests\Browser\Pages;

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
     * @param \Laravel\Dusk\Browser $browser The browser object
     *
     * @return void
     */
    public function assert($browser)
    {
        $browser->assertPathIs('/dashboard')
            ->waitUntilMissing('@app .app-loader')
            ->assertVisible('@links');
    }

    /**
     * Assert logged-in user
     *
     * @param \Laravel\Dusk\Browser $browser The browser object
     * @param string                $user    User email
     */
    public function assertUser($browser, $user)
    {
        $browser->assertVue('$store.state.authInfo.email', $user, '@dashboard-component');
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
        ];
    }
}
