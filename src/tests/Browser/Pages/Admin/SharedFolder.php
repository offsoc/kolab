<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Browser;
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
     */
    public function url(): string
    {
        return '/shared-folder/' . $this->folderId;
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
            ->waitFor('@folder-info');
    }

    /**
     * Get the element shortcuts for the page.
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
