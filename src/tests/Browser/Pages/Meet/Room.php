<?php

namespace Tests\Browser\Pages\Meet;

use Laravel\Dusk\Page;

class Room extends Page
{
    protected $roomName;

    /**
     * Object constructor.
     *
     * @param string $name Room name
     */
    public function __construct($name)
    {
        $this->roomName = $name;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url()
    {
        return '/meet/' . $this->roomName;
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
            ->waitUntilMissing('.app-loader')
            ->waitUntilMissing('#meet-setup div.status-message.loading');
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

            '@setup-form' => '#meet-setup form',
            '@setup-title' => '#meet-setup .card-title',
            '@setup-mic-select' => '#setup-microphone',
            '@setup-cam-select' => '#setup-camera',
            '@setup-nickname-input' => '#setup-nickname',
            '@setup-preview' => '#setup-preview',
            '@setup-volume' => '#setup-preview .volume',
            '@setup-video' => '#setup-preview video',
            '@setup-status-message' => '#meet-setup div.status-message',
            '@setup-button' => '#meet-setup form button',

            '@toolbar' => '#meet-session-toolbar',

            '@menu' => '#meet-session-menu',

            '@session' => '#meet-session',

            '@chat' => '#meet-chat',
            '@chat-input' => '#meet-chat textarea',
            '@chat-list' => '#meet-chat .chat',

            '@login-form' => '#meet-auth',
            '@login-email-input' => '#inputEmail',
            '@login-password-input' => '#inputPassword',
            '@login-second-factor-input' => '#secondfactor',
            '@login-button' => '#meet-auth button',
        ];
    }

    /**
     * Assert menu state.
     *
     * @param \Tests\Browser $browser The browser object
     * @param array          $menu    Menu items/state
     */
    public function assertToolbar($browser, array $menu): void
    {
        $browser->assertElementsCount('@menu button', count($menu));

        foreach ($menu as $item => $state) {
            $class = 'link-' . $item . ($state ? ':not(.text-danger)' : '.text-danger');
            $browser->assertVisible('@menu button.' . $class);
        }
    }

    /**
     * Submit logon form.
     *
     * @param \Tests\Browser $browser  The browser object
     * @param string         $username User name
     * @param string         $password User password
     * @param array          $config   Client-site config
     */
    public function submitLogon($browser, $username, $password, $config = []): void
    {
        $browser->type('@login-email-input', $username)
            ->type('@login-password-input', $password);

        if ($username == 'ned@kolab.org') {
            $code = \App\Auth\SecondFactor::code('ned@kolab.org');
            $browser->type('@login-second-factor-input', $code);
        }

        if (!empty($config)) {
            $browser->script(
                sprintf('Object.assign(window.config, %s)', \json_encode($config))
            );
        }

        $browser->click('@login-button');
    }
}
