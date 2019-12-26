<?php

namespace Tests\Browser;

use Tests\Browser\Components\Menu;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogonTest extends DuskTestCase
{

    /**
     * Test menu on logon page
     *
     * @return void
     */
    public function testLogonMenu()
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
     *
     * @return void
     */
    public function testLogonRedirect()
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
     *
     * @return void
     */
    public function testLogonWrongCredentials()
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
     *
     * @return void
     */
    public function testLogonSuccessful()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard());

            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['support', 'contact', 'webmail', 'logout']);
            });
        });
    }

    /**
     * Logout test
     *
     * @depends testLogonSuccessful
     * @return void
     */
    public function testLogout()
    {
        $this->browse(function (Browser $browser) {
            $browser->on(new Dashboard());

            // FIXME: Here we're testing click on Logout button
            //        We should also test if accessing /Logout url has the same effect
            $browser->within(new Menu(), function ($browser) {
                $browser->click('.link-logout');
            });

            // We expect the logoon page
            $browser->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'webmail']);
            });

            // TODO: Test if the session is really destroyed
        });
    }
}
