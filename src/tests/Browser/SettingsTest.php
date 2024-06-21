<?php

namespace Tests\Browser;

use App\User;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\TestCaseDusk;

class SettingsTest extends TestCaseDusk
{
    private $profile = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'currency' => 'USD',
        'country' => 'US',
        'billing_address' => "601 13th Street NW\nSuite 900 South\nWashington, DC 20005",
        'external_email' => 'john.doe.external@gmail.com',
        'phone' => '+1 509-248-1111',
        'organization' => 'Kolab Developers',
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        User::where('email', 'john@kolab.org')->first()->setSettings($this->profile);
        $this->deleteTestUser('profile-delete@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        User::where('email', 'john@kolab.org')->first()->setSettings($this->profile);
        $this->deleteTestUser('profile-delete@kolabnow.com');

        parent::tearDown();
    }

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
     * Test settings page (wallet controller)
     */
    public function testSettingsController(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $user->setSetting('password_policy', 'min:10,upper,digit');

            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-settings', 'My account')
                ->click('@links .link-settings')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info button.button-delete', 'Delete account')
                ->assertSeeIn('#user-info .card-title', 'My account')
                ->assertSeeIn('@nav #tab-general', 'General')
                ->with('@general', function (Browser $browser) use ($user) {
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Status (Customer No.)')
                        ->assertSeeIn('div.row:nth-child(1) #status', 'Active')
                        ->assertSeeIn('div.row:nth-child(1) #userid', "({$user->id})")
                        ->assertSeeIn('div.row:nth-child(2) label', 'Email')
                        ->assertValue('div.row:nth-child(2) input[type=text]', $user->email)
                        ->assertDisabled('div.row:nth-child(2) input[type=text]')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Email Aliases')
                        ->assertVisible('div.row:nth-child(3) .list-input')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue(['john.doe@kolab.org'])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(4) label', 'Password')
                        ->assertValue('div.row:nth-child(4) input#password', '')
                        ->assertValue('div.row:nth-child(4) input#password_confirmation', '')
                        ->assertAttribute('#password', 'placeholder', 'Password')
                        ->assertAttribute('#password_confirmation', 'placeholder', 'Confirm Password')
                        ->assertMissing('div.row:nth-child(4) .btn-group')
                        ->assertMissing('div.row:nth-child(4) #password-link')
                        ->assertSeeIn('div.row:nth-child(5) label', 'Subscriptions')
                        ->assertVisible('div.row:nth-child(5) table');
                })
                ->assertSeeIn('@nav #tab-settings', 'Settings')
                ->click('@nav #tab-settings')
                ->with('@settings', function (Browser $browser) {
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Greylisting')
                        ->click('div.row:nth-child(1) input[type=checkbox]');
                })
                ->assertSeeIn('@nav #tab-personal', 'Personal information')
                ->click('@nav #tab-personal')
                ->with('@personal', function (Browser $browser) {
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'First Name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', $this->profile['first_name'])
                        ->assertSeeIn('div.row:nth-child(2) label', 'Last Name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', $this->profile['last_name'])
                        ->assertSeeIn('div.row:nth-child(3) label', 'Organization')
                        ->assertValue('div.row:nth-child(3) input[type=text]', $this->profile['organization'])
                        ->assertSeeIn('div.row:nth-child(4) label', 'Phone')
                        ->assertValue('div.row:nth-child(4) input[type=text]', $this->profile['phone'])
                        ->assertSeeIn('div.row:nth-child(5) label', 'External Email')
                        ->assertValue('div.row:nth-child(5) input[type=text]', $this->profile['external_email'])
                        ->assertSeeIn('div.row:nth-child(6) label', 'Address')
                        ->assertValue('div.row:nth-child(6) textarea', $this->profile['billing_address'])
                        ->assertSeeIn('div.row:nth-child(7) label', 'Country')
                        ->assertValue('div.row:nth-child(7) select', $this->profile['country'])
                        // Set some fields and submit
                        ->type('#first_name', 'Arnie')
                        ->vueClear('#last_name')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
        });
    }

    /**
     * Test settings page (non-controller user)
     */
    public function testProfileNonController(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('password_policy', 'min:10,upper,digit');

        // Test acting as non-controller
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-settings', 'My account')
                ->click('@links .link-settings')
                ->on(new UserInfo())
                ->assertMissing('#user-info button.button-delete')
                ->assertSeeIn('#user-info .card-title', 'My account')
                ->assertSeeIn('@nav #tab-general', 'General')
                ->with('@general', function (Browser $browser) {
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Email')
                        ->assertValue('div.row:nth-child(1) input[type=text]', 'jack@kolab.org')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Password')
                        ->assertValue('div.row:nth-child(2) input#password', '')
                        ->assertValue('div.row:nth-child(2) input#password_confirmation', '')
                        ->assertAttribute('#password', 'placeholder', 'Password')
                        ->assertAttribute('#password_confirmation', 'placeholder', 'Confirm Password')
                        ->assertMissing('div.row:nth-child(2) .btn-group')
                        ->assertMissing('div.row:nth-child(2) #password-link')
                        ->assertMissing('div.row:nth-child(3)')
                        ->whenAvailable('#password_policy', function (Browser $browser) {
                            $browser->assertElementsCount('li', 3)
                                ->assertMissing('li:nth-child(1) svg.text-success')
                                ->assertSeeIn('li:nth-child(1) small', "Minimum password length: 10 characters")
                                ->assertMissing('li:nth-child(2) svg.text-success')
                                ->assertSeeIn('li:nth-child(2) small', "Password contains an upper-case character")
                                ->assertMissing('li:nth-child(3) svg.text-success')
                                ->assertSeeIn('li:nth-child(3) small', "Password contains a digit");
                        });
                })
                ->assertMissing('@nav #tab-settings')
                ->assertSeeIn('@nav #tab-personal', 'Personal information')
                ->click('@nav #tab-personal')
                ->with('@personal', function (Browser $browser) {
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'First Name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', 'Jack')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Last Name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', 'Daniels')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Organization')
                        ->assertSeeIn('div.row:nth-child(4) label', 'Phone')
                        ->assertSeeIn('div.row:nth-child(5) label', 'External Email')
                        ->assertSeeIn('div.row:nth-child(6) label', 'Address')
                        ->assertSeeIn('div.row:nth-child(7) label', 'Country')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
        });

        $user = $this->getTestUser('profile-delete@kolabnow.com', ['password' => 'simple123']);
        $oldpassword = $user->password;

        // Test password change
        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new Home())
                ->submitLogon($user->email, 'simple123', true)
                ->on(new Dashboard())
                ->click('@links .link-settings')
                ->on(new UserInfo())
                ->assertSeeIn('@nav #tab-general', 'General')
                ->with('@general', function (Browser $browser) {
                    $browser
                        ->type('input#password', '12345678')
                        ->type('input#password_confirmation', '12345678')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
        });

        $this->assertTrue($oldpassword != $user->fresh()->password);
    }

    /**
     * Test deleting an account
     */
    public function testAccountDelete(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('profile-delete@kolabnow.com', ['password' => 'simple123']);

            $browser->visit(new Home())
                ->submitLogon('profile-delete@kolabnow.com', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-settings', 'My account')
                ->click('@links .link-settings')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info button.button-delete', 'Delete account')
                ->click('#user-info button.button-delete')
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Delete this account?')
                        ->assertSeeIn('@body', 'This will delete the account as well as all domains')
                        ->assertSeeIn('@body strong', 'This operation is irreversible')
                        ->assertFocused('@button-cancel')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Delete account')
                        ->click('@button-cancel');
                })
                ->waitUntilMissing('#delete-warning')
                ->click('#user-info button.button-delete')
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->click('@button-action');
                })
                ->waitUntilMissing('#delete-warning')
                ->assertToast(Toast::TYPE_SUCCESS, 'User deleted successfully.')
                ->on(new Home());

            $this->assertTrue($user->fresh()->trashed());
        });
    }
}
