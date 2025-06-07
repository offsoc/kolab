<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Policies;
use Tests\TestCaseDusk;

class PoliciesTest extends TestCaseDusk
{
    protected function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
            'itip_policy' => null,
            'externalsender_policy' => null,
            'greylist_policy' => null,
        ]);

        parent::tearDown();
    }

    /**
     * Test Policies page (unauthenticated)
     */
    public function testPoliciesUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(static function (Browser $browser) {
            $browser->visit('/policies')->on(new Home());
        });
    }

    /**
     * Test Policies "box" on Dashboard
     */
    public function testDashboard(): void
    {
        $this->browse(static function (Browser $browser) {
            // Test a user that is not an account owner
            $browser->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertMissing('@links .link-policies')
                ->visit('/policies')
                ->assertErrorPage(403)
                ->within(new Menu(), static function (Browser $browser) {
                    $browser->clickMenuItem('logout');
                });

            // Test the account owner
            $browser->waitForLocation('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-policies svg + span', 'Policies');
        });
    }

    /**
     * Test password policies page
     *
     * @depends testDashboard
     */
    public function testPasswordPolicies(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSetting('password_policy', 'min:5,max:100,lower');
        $john->setSetting('max_password_age', null);

        $this->browse(static function (Browser $browser) {
            $browser->click('@links .link-policies')
                ->on(new Policies())
                ->assertSeeIn('#policies .card-title', 'Policies')
                ->assertSeeIn('#policies .nav-item:nth-child(1)', 'Password')
                ->assertSeeIn('@password-form .row:nth-child(1) > label', 'Password Policy')
                ->with('@password-form #password_policy', static function (Browser $browser) {
                    $browser->assertElementsCount('li', 7)
                        ->assertSeeIn('li:nth-child(1) label', 'Minimum password length')
                        ->assertChecked('li:nth-child(1) input[type=checkbox]')
                        ->assertDisabled('li:nth-child(1) input[type=checkbox]')
                        ->assertValue('li:nth-child(1) input[type=text]', '5')
                        ->assertSeeIn('li:nth-child(2) label', 'Maximum password length')
                        ->assertChecked('li:nth-child(2) input[type=checkbox]')
                        ->assertDisabled('li:nth-child(2) input[type=checkbox]')
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
                        ->assertSeeIn('li:nth-child(7) label', 'Password cannot be the same as the last')
                        ->assertNotChecked('li:nth-child(7) input[type=checkbox]')
                        ->assertMissing('li:nth-child(7) input[type=text]')
                        ->assertSelected('li:nth-child(7) select', 3)
                        ->assertSelectHasOptions('li:nth-child(7) select', [1, 2, 3, 4, 5, 6])
                        // Change the policy
                        ->type('li:nth-child(1) input[type=text]', '11')
                        ->type('li:nth-child(2) input[type=text]', '120')
                        ->click('li:nth-child(3) input[type=checkbox]')
                        ->click('li:nth-child(4) input[type=checkbox]');
                })
                ->assertSeeIn('@password-form .row:nth-child(2) > label', 'Password Retention')
                ->with('@password-form #password_retention', static function (Browser $browser) {
                    $browser->assertElementsCount('li', 1)
                        ->assertSeeIn('li:nth-child(1) label', 'Require a password change every')
                        ->assertNotChecked('li:nth-child(1) input[type=checkbox]')
                        ->assertSelected('li:nth-child(1) select', 3)
                        ->assertSelectHasOptions('li:nth-child(1) select', [3, 6, 9, 12])
                        // change the policy
                        ->check('li:nth-child(1) input[type=checkbox]')
                        ->select('li:nth-child(1) select', 6);
                })
                ->click('@password-form button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User settings updated successfully.');
        });

        $this->assertSame('min:11,max:120,upper', $john->getSetting('password_policy'));
        $this->assertSame('6', $john->getSetting('max_password_age'));
    }

    /**
     * Test maildelivery policies page
     *
     * @depends testDashboard
     */
    public function testMailDeliveryPolicies(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
            'itip_policy' => 'true',
            'externalsender_policy' => null,
            'greylist_policy' => null,
        ]);

        $this->browse(static function (Browser $browser) {
            $browser->visit('/policies')
                ->on(new Policies())
                ->assertSeeIn('#policies .card-title', 'Policies')
                ->assertSeeIn('#policies .nav-item:nth-child(2)', 'Mail delivery')
                ->click('#policies .nav-item:nth-child(2)')
                ->with('@maildelivery-form', static function (Browser $browser) {
                    $browser->assertElementsCount('div.row', 3)
                        ->assertSeeIn('div.row:nth-child(1) label', 'Greylisting')
                        ->assertChecked('div.row:nth-child(1) input[type=checkbox]')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Calendar invitations')
                        ->assertChecked('div.row:nth-child(2) input[type=checkbox]')
                        ->assertSeeIn('div.row:nth-child(3) label', 'External sender warning')
                        ->assertNotChecked('div.row:nth-child(3) input[type=checkbox]')
                        // Change the policy
                        ->click('div.row:nth-child(1) input[type=checkbox]')
                        ->click('div.row:nth-child(2) input[type=checkbox]')
                        ->click('div.row:nth-child(3) input[type=checkbox]')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User settings updated successfully.');
        });

        $this->assertSame('false', $john->getSetting('greylist_policy'));
        $this->assertSame('false', $john->getSetting('itip_policy'));
        $this->assertSame('true', $john->getSetting('externalsender_policy'));
    }
}
