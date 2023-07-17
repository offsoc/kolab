<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Page;

class SharedFolder extends Page
{
    protected $folderId;

    /**
     * Object constructor.
     *
     * @param int $id Shared folder Id
     */
    public function __construct($id)
    {
        $this->folderId = $id;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/shared-folder/' . $this->folderId;
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
            ->waitFor('@folder-info');
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
            '@folder-info' => '#folder-info',
            '@folder-settings' => '#settings',
            '@folder-aliases' => '#aliases',
        ];
    }
}
