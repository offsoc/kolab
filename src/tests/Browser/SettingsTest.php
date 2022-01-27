<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Settings;
use Tests\TestCaseDusk;

class SettingsTest extends TestCaseDusk
{
    /**
     * Test settings page (unauthenticated)
     */
    public function testSettingsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/settings')->on(new Home());
        });
    }

    /**
     * Test settings "box" on Dashboard
     */
    public function testDashboard(): void
    {
        $this->browse(function (Browser $browser) {
            // Test a user that is not an account owner
            $browser->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertMissing('@links .link-settings .name')
                ->visit('/settings')
                ->assertErrorPage(403)
                ->within(new Menu(), function (Browser $browser) {
                    $browser->clickMenuItem('logout');
                });

            // Test the account owner
            $browser->waitForLocation('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-settings .name', 'Settings');
        });
    }

    /**
     * Test Settings page
     *
     * @depends testDashboard
     */
    public function testSettings(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSetting('password_policy', 'min:5,max:100,lower');

        $this->browse(function (Browser $browser) {
            $browser->click('@links .link-settings')
                ->on(new Settings())
                ->assertSeeIn('#settings .card-title', 'Settings')
                // Password policy
                ->assertSeeIn('@form .row:nth-child(1) > label', 'Password Policy')
                ->with('@form #password_policy', function (Browser $browser) {
                    $browser->assertElementsCount('li', 6)
                        ->assertSeeIn('li:nth-child(1) label', 'Minimum password length')
                        ->assertChecked('li:nth-child(1) input[type=checkbox]')
                        ->assertValue('li:nth-child(1) input[type=text]', '5')
                        ->assertSeeIn('li:nth-child(2) label', 'Maximum password length')
                        ->assertChecked('li:nth-child(2) input[type=checkbox]')
                        ->assertValue('li:nth-child(2) input[type=text]', '100')
                        ->assertSeeIn('li:nth-child(3) label', 'Password contains a lower-case character')
                        ->assertChecked('li:nth-child(3) input[type=checkbox]')
                        ->assertMissing('li:nth-child(3) input[type=text]')
                        ->assertSeeIn('li:nth-child(4) label', 'Password contains an upper-case character')
                        ->assertNotChecked('li:nth-child(4) input[type=checkbox]')
                        ->assertMissing('li:nth-child(4) input[type=text]')
                        ->assertSeeIn('li:nth-child(5) label', 'Password contains a digit')
                        ->assertNotChecked('li:nth-child(5) input[type=checkbox]')
                        ->assertMissing('li:nth-child(5) input[type=text]')
                        ->assertSeeIn('li:nth-child(6) label', 'Password contains a special character')
                        ->assertNotChecked('li:nth-child(6) input[type=checkbox]')
                        ->assertMissing('li:nth-child(6) input[type=text]')
                        // Change the policy
                        ->type('li:nth-child(1) input[type=text]', '11')
                        ->type('li:nth-child(2) input[type=text]', '120')
                        ->click('li:nth-child(3) input[type=checkbox]')
                        ->click('li:nth-child(4) input[type=checkbox]');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User settings updated successfully.');
        });

        $this->assertSame('min:11,max:120,upper', $john->getSetting('password_policy'));
    }
}
