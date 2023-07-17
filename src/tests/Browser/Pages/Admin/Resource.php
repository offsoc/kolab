<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Page;

class Resource extends Page
{
    protected $resourceId;

    /**
     * Object constructor.
     *
     * @param int $id Resource Id
     */
    public function __construct($id)
    {
        $this->resourceId = $id;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/resource/' . $this->resourceId;
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
            ->waitFor('@resource-info');
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
            '@resource-info' => '#resource-info',
            '@resource-settings' => '#settings',
        ];
    }
}
