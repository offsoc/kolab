<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Page;

class User extends Page
{
    protected $userid;

    /**
     * Object constructor.
     *
     * @param int $userid User Id
     */
    public function __construct($userid)
    {
        $this->userid = $userid;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/user/' . $this->userid;
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param \Laravel\Dusk\Browser $browser The browser object
     *
     * @return void
     */
    public function assert($browser): void
    {
        $browser->waitForLocation($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->waitFor('@user-info');
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
            '@user-info' => '#user-info',
            '@nav' => 'ul.nav-tabs',
            '@user-finances' => '#finances',
            '@user-aliases' => '#aliases',
            '@user-subscriptions' => '#subscriptions',
            '@user-distlists' => '#distlists',
            '@user-domains' => '#domains',
            '@user-history' => '#history',
            '@user-resources' => '#resources',
            '@user-folders' => '#folders',
            '@user-users' => '#users',
            '@user-settings' => '#settings',
        ];
    }
}
