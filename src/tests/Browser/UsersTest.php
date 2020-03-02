<?php

namespace Tests\Browser;

use App\User;
use App\UserAlias;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\Browser\Pages\UserList;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UsersTest extends DuskTestCase
{
    private $profile = [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // TODO: Use TestCase::deleteTestUser()
        User::withTrashed()->where('email', 'john.rambo@kolab.org')->forceDelete();

        $john = User::where('email', 'john@kolab.org')->first();
        $john->setSettings($this->profile);
        UserAlias::where('user_id', $john->id)
            ->where('alias', 'john.test@kolab.org')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        // TODO: Use TestCase::deleteTestUser()
        User::withTrashed()->where('email', 'john.rambo@kolab.org')->forceDelete();

        $john = User::where('email', 'john@kolab.org')->first();
        $john->setSettings($this->profile);
        UserAlias::where('user_id', $john->id)
            ->where('alias', 'john.test@kolab.org')->delete();

        parent::tearDown();
    }

    /**
     * Test user info page (unauthenticated)
     */
    public function testInfoUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $user = User::where('email', 'john@kolab.org')->first();

            $browser->visit('/user/' . $user->id)->on(new Home());
        });
    }

    /**
     * Test users list page (unauthenticated)
     */
    public function testListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/users')->on(new Home());
        });

        // TODO: Test that jack@kolab.org can't access this page
    }

    /**
     * Test users list page
     */
    public function testList(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-users', 'User accounts')
                ->click('@links .link-users')
                ->on(new UserList())
                ->whenAvailable('@table', function ($browser) {
                    $this->assertCount(1, $browser->elements('tbody tr'));
                    $browser->assertSeeIn('tbody tr td a', 'john@kolab.org');
                });
        });
    }

    /**
     * Test user account editing page (not profile page)
     *
     * @depends testList
     */
    public function testInfo(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->on(new UserList())
                ->click('@table tr:first-child a')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info .card-title', 'User account')
                ->with('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertFocused('div.row:nth-child(1) input')
                        ->assertSeeIn('div.row:nth-child(1) label', 'First name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', $this->profile['first_name'])
                        ->assertSeeIn('div.row:nth-child(2) label', 'Last name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', $this->profile['last_name'])
                        ->assertSeeIn('div.row:nth-child(3) label', 'Email')
                        ->assertValue('div.row:nth-child(3) input[type=text]', 'john@kolab.org')
//TODO                        ->assertDisabled('div.row:nth-child(3) input')
                        ->assertSeeIn('div.row:nth-child(4) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(4) .listinput-widget')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue(['john.doe@kolab.org'])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(5) label', 'Password')
                        ->assertValue('div.row:nth-child(5) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(6) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(6) input[type=password]', '')
                        ->assertSeeIn('button[type=submit]', 'Submit');

                    // Clear some fields and submit
                    $browser->type('#first_name', '')
                        ->type('#last_name', '')
                        ->click('button[type=submit]');
                })
                ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                    $browser->assertToastTitle('')
                        ->assertToastMessage('User data updated successfully')
                        ->closeToast();
                });

            // Test error handling (password)
            $browser->with('@form', function (Browser $browser) {
                $browser->type('#password', 'aaaaaa')
                    ->type('#password_confirmation', '')
                    ->click('button[type=submit]')
                    ->waitFor('#password + .invalid-feedback')
                    ->assertSeeIn('#password + .invalid-feedback', 'The password confirmation does not match.')
                    ->assertFocused('#password');
            })
            ->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                $browser->assertToastTitle('Error')
                    ->assertToastMessage('Form validation error')
                    ->closeToast();
            });

            // TODO: Test password change

            // Test form error handling (aliases)
            $browser->with('@form', function (Browser $browser) {
                // TODO: For some reason, clearing the input value
                // with ->type('#password', '') does not work, maybe some dusk/vue intricacy
                // For now we just use the default password
                $browser->type('#password', 'simple123')
                    ->type('#password_confirmation', 'simple123')
                    ->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->addListEntry('invalid address');
                    })
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                $browser->assertToastTitle('Error')
                    ->assertToastMessage('Form validation error')
                    ->closeToast();
            })
            ->with('@form', function (Browser $browser) {
                $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->assertFormError(2, 'The specified alias is invalid.', false);
                });
            });

            // Test adding aliases
            $browser->with('@form', function (Browser $browser) {
                $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->removeListEntry(2)
                        ->addListEntry('john.test@kolab.org');
                })
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('User data updated successfully')
                    ->closeToast();
            });

            $john = User::where('email', 'john@kolab.org')->first();
            $alias = UserAlias::where('user_id', $john->id)->where('alias', 'john.test@kolab.org')->first();
            $this->assertTrue(!empty($alias));
        });
    }

    /**
     * Test user adding page
     *
     * @depends testList
     */
    public function testNewUser(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new UserList())
                ->assertSeeIn('button.create-user', 'Create user')
                ->click('button.create-user')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info .card-title', 'New user account')
                ->with('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertFocused('div.row:nth-child(1) input')
                        ->assertSeeIn('div.row:nth-child(1) label', 'First name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', '')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Last name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', '')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Email')
                        ->assertValue('div.row:nth-child(3) input[type=text]', '')
                        ->assertEnabled('div.row:nth-child(3) input')
                        ->assertSeeIn('div.row:nth-child(4) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(4) .listinput-widget')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue([])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(5) label', 'Password')
                        ->assertValue('div.row:nth-child(5) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(6) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(6) input[type=password]', '')
                        ->assertSeeIn('button[type=submit]', 'Submit');

                    // Test browser-side required fields and error handling
                    $browser->click('button[type=submit]')
                        ->assertFocused('#email')
                        ->type('#email', 'invalid email')
                        ->click('button[type=submit]')
                        ->assertFocused('#password')
                        ->type('#password', 'simple123')
                        ->click('button[type=submit]')
                        ->assertFocused('#password_confirmation')
                        ->type('#password_confirmation', 'simple')
                        ->click('button[type=submit]');
                })
                ->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                    $browser->assertToastTitle('Error')
                        ->assertToastMessage('Form validation error')
                        ->closeToast();
                })
                ->with('@form', function (Browser $browser) {
                    $browser->assertSeeIn('#email + .invalid-feedback', 'The specified email is invalid.')
                        ->assertSeeIn('#password + .invalid-feedback', 'The password confirmation does not match.');
                });

            // Test form error handling (aliases)
            $browser->with('@form', function (Browser $browser) {
                $browser->type('#email', 'john.rambo@kolab.org')
                    ->type('#password_confirmation', 'simple123')
                    ->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->addListEntry('invalid address');
                    })
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_ERROR), function (Browser $browser) {
                $browser->assertToastTitle('Error')
                    ->assertToastMessage('Form validation error')
                    ->closeToast();
            })
            ->with('@form', function (Browser $browser) {
                $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->assertFormError(1, 'The specified alias is invalid.', false);
                });
            });

            // Successful account creation
            $browser->with('@form', function (Browser $browser) {
                $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->removeListEntry(1)
                        ->addListEntry('john.rambo2@kolab.org');
                })
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('User created successfully')
                    ->closeToast();
            });

            // TODO: assert redirect to users list

            $john = User::where('email', 'john.rambo@kolab.org')->first();
            $alias = UserAlias::where('user_id', $john->id)->where('alias', 'john.rambo2@kolab.org')->first();
            $this->assertTrue(!empty($alias));
        });
    }
}
