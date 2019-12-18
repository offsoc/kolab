<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Home extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url()
    {
        return '/';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param  \Laravel\Dusk\Browser  $browser
     * @return void
     */
    public function assert(Browser $browser)
    {
        $browser->assertPathIs('/login');
        $browser->assertVisible('form.form-signin');
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@app' => '#app',
        ];
    }

    /**
     * Submit logon form.
     *
     * @param Browser $browser
     * @param string  $username
     * @param string  $password
     * @param bool    $wait_for_dashboard
     *
     * @return void
     */
    public function submitLogon(Browser $browser, $username, $password, $wait_for_dashboard = false)
    {
        $browser
            ->type('#inputEmail', $username)
            ->type('#inputPassword', $password)
            ->press('form button');

        if ($wait_for_dashboard) {
            $browser->waitForLocation('/dashboard');
        }
    }
}
