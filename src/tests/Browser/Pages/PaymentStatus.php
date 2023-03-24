<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class PaymentStatus extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/payment/status';
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
        $browser->waitForLocation($this->url())
            ->waitUntilMissing('@app .app-loader');
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
            '@content' => '.card .card-text',
            '@lock-alert' => '#lock-alert',
            '@button' => '.card button.btn-primary',
        ];
    }
}
