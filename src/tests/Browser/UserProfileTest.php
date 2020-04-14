<?php

namespace Tests\Browser;

use App\User;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserProfile;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserProfileTest extends TestCaseDusk
{
    private $profile = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'currency' => 'USD',
        'country' => 'US',
        'billing_address' => "601 13th Street NW\nSuite 900 South\nWashington, DC 20005",
        'external_email' => 'john.doe.external@gmail.com',
        'phone' => '+1 509-248-1111',
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
     * Test profile page (unauthenticated)
     */
    public function testProfileUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/profile')->on(new Home());
        });
    }

    /**
     * Test profile page
     */
    public function testProfile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-profile', 'Your profile')
                ->click('@links .link-profile')
                ->on(new UserProfile())
                ->assertSeeIn('#user-profile .button-delete', 'Delete account')
                ->whenAvailable('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertFocused('div.row:nth-child(1) input')
                        ->assertSeeIn('div.row:nth-child(1) label', 'First name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', $this->profile['first_name'])
                        ->assertSeeIn('div.row:nth-child(2) label', 'Last name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', $this->profile['last_name'])
                        ->assertSeeIn('div.row:nth-child(3) label', 'Phone')
                        ->assertValue('div.row:nth-child(3) input[type=text]', $this->profile['phone'])
                        ->assertSeeIn('div.row:nth-child(4) label', 'External email')
                        ->assertValue('div.row:nth-child(4) input[type=text]', $this->profile['external_email'])
                        ->assertSeeIn('div.row:nth-child(5) label', 'Address')
                        ->assertValue('div.row:nth-child(5) textarea', $this->profile['billing_address'])
                        ->assertSeeIn('div.row:nth-child(6) label', 'Country')
                        ->assertValue('div.row:nth-child(6) select', $this->profile['country'])
                        ->assertSeeIn('div.row:nth-child(7) label', 'Password')
                        ->assertValue('div.row:nth-child(7) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(8) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(8) input[type=password]', '')
                        ->assertSeeIn('button[type=submit]', 'Submit');

                    // Clear all fields and submit
                    // FIXME: Should any of these fields be required?
                    $browser->type('#first_name', '')
                        ->type('#last_name', '')
                        ->type('#phone', '')
                        ->type('#external_email', '')
                        ->type('#billing_address', '')
                        ->select('#country', '')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');

            // Test error handling
            $browser->with('@form', function (Browser $browser) {
                $browser->type('#phone', 'aaaaaa')
                    ->type('#external_email', 'bbbbb')
                    ->click('button[type=submit]')
                    ->waitFor('#phone + .invalid-feedback')
                    ->assertSeeIn('#phone + .invalid-feedback', 'The phone format is invalid.')
                    ->assertSeeIn(
                        '#external_email + .invalid-feedback',
                        'The external email must be a valid email address.'
                    )
                    ->assertFocused('#phone')
                    ->assertToast(Toast::TYPE_ERROR, 'Form validation error');
            });
        });
    }

    /**
     * Test profile of non-controller user
     */
    public function testProfileNonController(): void
    {
        // Test acting as non-controller
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-profile', 'Your profile')
                ->click('@links .link-profile')
                ->on(new UserProfile())
                ->assertMissing('#user-profile .button-delete')
                ->whenAvailable('@form', function (Browser $browser) {
                    // TODO: decide on what fields the non-controller user should be able
                    //       to see/change
                });

            // Test that /profile/delete page is not accessible
            $browser->visit('/profile/delete')
                ->assertErrorPage(403);
        });
    }

    /**
     * Test profile delete page
     */
    public function testProfileDelete(): void
    {
        $user = $this->getTestUser('profile-delete@kolabnow.com', ['password' => 'simple123']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/logout')
                ->on(new Home())
                ->submitLogon('profile-delete@kolabnow.com', 'simple123', true)
                ->on(new Dashboard())
                ->clearToasts()
                ->assertSeeIn('@links .link-profile', 'Your profile')
                ->click('@links .link-profile')
                ->on(new UserProfile())
                ->click('#user-profile .button-delete')
                ->waitForLocation('/profile/delete')
                ->assertSeeIn('#user-delete .card-title', 'Delete this account?')
                ->assertSeeIn('#user-delete .button-cancel', 'Cancel')
                ->assertSeeIn('#user-delete .card-text', 'This operation is irreversible')
                ->assertFocused('#user-delete .button-cancel')
                ->click('#user-delete .button-cancel')
                ->waitForLocation('/profile')
                ->on(new UserProfile());

            // Test deleting the user
            $browser->click('#user-profile .button-delete')
                ->waitForLocation('/profile/delete')
                ->click('#user-delete .button-delete')
                ->waitForLocation('/login')
                ->assertToast(Toast::TYPE_SUCCESS, 'User deleted successfully.');

            $this->assertTrue($user->fresh()->trashed());
        });
    }

    // TODO: Test that Ned (John's "delegatee") can delete himself
    // TODO: Test that Ned (John's "delegatee") can/can't delete John ?
}
