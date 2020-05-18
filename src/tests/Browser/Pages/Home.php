<?php

namespace Tests\Browser\Pages;

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
        return '/login';
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
            ->assertVisible('form.form-signin');
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
            '@email-input' => '#inputEmail',
            '@password-input' => '#inputPassword',
            '@second-factor-input' => '#secondfactor',
        ];
    }

    /**
     * Submit logon form.
     *
     * @param \Laravel\Dusk\Browser $browser  The browser object
     * @param string                $username User name
     * @param string                $password User password
     * @param bool                  $wait_for_dashboard
     * @param array                 $config   Client-site config
     *
     * @return void
     */
    public function submitLogon(
        $browser,
        $username,
        $password,
        $wait_for_dashboard = false,
        $config = []
    ) {
        $browser->type('@email-input', $username)
            ->type('@password-input', $password);

        if ($username == 'ned@kolab.org') {
            $code = \App\Auth\SecondFactor::code('ned@kolab.org');
            $browser->type('@second-factor-input', $code);
        }

        if (!empty($config)) {
            $browser->script(
                sprintf('Object.assign(window.config, %s)', \json_encode($config))
            );
        }

        $browser->press('form button');

        if ($wait_for_dashboard) {
            $browser->waitForLocation('/dashboard');
        }
    }
}
