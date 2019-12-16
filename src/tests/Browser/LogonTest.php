<?php

namespace Tests\Browser;

use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogonTest extends DuskTestCase
{

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
        });
    }
}
