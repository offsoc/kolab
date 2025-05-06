<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Distlist extends Page
{
    protected $listid;

    /**
     * Object constructor.
     *
     * @param int $listid Distribution list Id
     */
    public function __construct($listid)
    {
        $this->listid = $listid;
    }

    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/distlist/' . $this->listid;
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser): void
    {
        $browser->waitForLocation($this->url())
            ->waitUntilMissing('@app .app-loader')
            ->waitFor('@distlist-info');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@distlist-info' => '#distlist-info',
            '@distlist-settings' => '#settings',
            '@distlist-history' => '#history',
        ];
    }
}
