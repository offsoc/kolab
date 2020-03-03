<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page;

class PasswordReset extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/password-reset';
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
        $browser->assertPathIs('/password-reset');
        $browser->assertPresent('@step1');
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
            '@step1' => '#step1',
            '@step2' => '#step2',
            '@step3' => '#step3',
        ];
    }
}
