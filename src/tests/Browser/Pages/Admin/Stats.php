<?php

namespace Tests\Browser\Pages\Admin;

use Laravel\Dusk\Page;

class Stats extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/stats';
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
            ->waitFor('@container');
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
            '@container' => '#stats-container',
        ];
    }
}
