<?php

namespace Tests\Browser\Pages\Admin;

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
     *
     * @return string
     */
    public function url(): string
    {
        return '/distlist/' . $this->listid;
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
            ->waitFor('@distlist-info');
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
            '@distlist-info' => '#distlist-info',
            '@distlist-config' => '#distlist-config',
        ];
    }
}
