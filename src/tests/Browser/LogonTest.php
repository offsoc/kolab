<?php

namespace Tests\Browser;

use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogonTest extends DuskTestCase
{

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
                ->submitLogon('john@kolab.org', 'wrong');

            // Checks if we're still on the logon page
            // FIXME: This assertion might be prone to timing issues
            // I guess we should wait until some error message appears
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
                ->submitLogon('john@kolab.org', 'simple123', true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard());

            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['support', 'contact', 'webmail', 'logout']);
            });

            $browser->assertVue('data.email', 'john@kolab.org', '@dashboard-component');
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
                ->submitLogon('john@kolab.org', 'simple123', true);

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
