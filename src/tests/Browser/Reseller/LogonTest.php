<?php

namespace Tests\Browser\Reseller;

use Tests\Browser;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class LogonTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    /**
     * Test menu on logon page
     */
    public function testLogonMenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->with(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['support', 'login', 'lang']);
                })
                ->assertMissing('@second-factor-input')
                ->assertMissing('@forgot-password');
        });
    }

    /**
     * Test redirect to /login if user is unauthenticated
     */
    public function testLogonRedirect(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard');

            // Checks if we're really on the login page
            $browser->waitForLocation('/login')
                ->on(new Home());
        });
    }

    /**
     * Logon with wrong password/user test
     */
    public function testLogonWrongCredentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), 'wrong')
                // Error message
                ->assertToast(Toast::TYPE_ERROR, 'Invalid username or password.')
                // Checks if we're still on the logon page
                ->on(new Home());
        });
    }

    /**
     * Successful logon test
     */
    public function testLogonSuccessful(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), \App\Utils::generatePassphrase(), true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard())
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['support', 'dashboard', 'logout', 'lang']);
                })
                ->assertUser('reseller@' . \config('app.domain'));

            // Test that visiting '/' with logged in user does not open logon form
            // but "redirects" to the dashboard
            $browser->visit('/')->on(new Dashboard());
        });
    }

    /**
     * Logout test
     *
     * @depends testLogonSuccessful
     */
    public function testLogout(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->on(new Dashboard());

            // Click the Logout button
            $browser->within(new Menu(), function ($browser) {
                $browser->clickMenuItem('logout');
            });

            // We expect the logon page
            $browser->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['support', 'login', 'lang']);
            });

            // Success toast message
            $browser->assertToast(Toast::TYPE_SUCCESS, 'Successfully logged out');
        });
    }

    /**
     * Logout by URL test
     */
    public function testLogoutByURL(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), \App\Utils::generatePassphrase(), true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard());

            // Use /logout url, and expect the logon page
            $browser->visit('/logout')
                ->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['support', 'login', 'lang']);
            });

            // Success toast message
            $browser->assertToast(Toast::TYPE_SUCCESS, 'Successfully logged out');
        });
    }
}
