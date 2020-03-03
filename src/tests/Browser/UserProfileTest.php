<?php

namespace Tests\Browser;

use App\User;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserProfile;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserProfileTest extends DuskTestCase
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
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        User::where('email', 'john@kolab.org')->first()->setSettings($this->profile);

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
                ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                    $browser->assertToastTitle('')
                        ->assertToastMessage('User data updated successfully')
                        ->closeToast();
                });


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
                    ->assertFocused('#phone');
            })
            ->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                $browser->assertToastTitle('Error')
                    ->assertToastMessage('Form validation error')
                    ->closeToast();
            });
        });
    }
}
