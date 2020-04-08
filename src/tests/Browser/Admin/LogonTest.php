<?php

namespace Tests\Browser\Admin;

use Tests\Browser;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogonTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // This will set baseURL for all tests in this file
        // If we wanted to visit both user and admin in one test
        // we can also just call visit() with full url
        Browser::$baseUrl = str_replace('//', '//admin.', \config('app.url'));
    }

    /**
     * Test menu on logon page
     */
    public function testLogonMenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home());
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'webmail']);
            });
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
                ->submitLogon('jeroen@jeroen.jeroen', 'wrong');

            // Error message
            $browser->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                $browser->assertToastTitle('Error')
                    ->assertToastMessage('Invalid username or password.')
                    ->closeToast();
            });

            // Checks if we're still on the logon page
            $browser->on(new Home());
        });
    }

    /**
     * Successful logon test
     */
    public function testLogonSuccessful(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', 'jeroen', true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard())
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['support', 'contact', 'webmail', 'logout']);
                })
                ->assertUser('jeroen@jeroen.jeroen');

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
                $browser->click('.link-logout');
            });

            // We expect the logon page
            $browser->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'webmail']);
            });

            // Success toast message
            $browser->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('Successfully logged out')
                    ->closeToast();
            });
        });
    }

    /**
     * Logout by URL test
     */
    public function testLogoutByURL(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', 'jeroen', true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard());

            // Use /logout url, and expect the logon page
            $browser->visit('/logout')
                ->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'webmail']);
            });

            // Success toast message
            $browser->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('Successfully logged out')
                    ->closeToast();
            });
        });
    }
}
