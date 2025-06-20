<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Signup extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/signup';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->assertPathIs('/signup')
            ->waitUntilMissing('.app-loader')
            ->assertPresent('@step0')
            ->assertPresent('@step1')
            ->assertPresent('@step2')
            ->assertPresent('@step3');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@app' => '#app',
            '@step0' => '#step0',
            '@step1' => '#step1',
            '@step2' => '#step2',
            '@step3' => '#step3',
            '@step4' => '#step4',
        ];
    }
}
