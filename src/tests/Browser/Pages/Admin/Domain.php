<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Page;

class Domain extends Page
{
    protected $domainid;

    /**
     * Object constructor.
     *
     * @param int $domainid Domain Id
     */
    public function __construct($domainid)
    {
        $this->domainid = $domainid;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/domain/' . $this->domainid;
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
            ->waitFor('@domain-info');
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
            '@domain-info' => '#domain-info',
            '@nav' => 'ul.nav-tabs',
            '@domain-config' => '#config',
            '@domain-settings' => '#settings',
            '@domain-history' => '#history',
        ];
    }
}
