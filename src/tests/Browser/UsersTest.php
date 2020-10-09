<?php

namespace Tests\Browser;

use App\Discount;
use App\Entitlement;
use App\Sku;
use App\User;
use App\UserAlias;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\QuotaInput;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\Browser\Pages\UserList;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UsersTest extends TestCaseDusk
{
    private $password;

    private $profile = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'organization' => 'Test Domain Owner',
    ];

    private $users = [];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->password = \App\Utils::generatePassphrase();

        $this->domain = $this->getTestDomain(
            'test.domain',
            [
                'type' => \App\Domain::TYPE_EXTERNAL,
                'status' => \App\Domain::STATUS_ACTIVE | \App\Domain::STATUS_CONFIRMED | \App\Domain::STATUS_VERIFIED
            ]
        );

        $packageKolab = \App\Package::where('title', 'kolab')->first();

        $this->owner = $this->getTestUser('john@test.domain', ['password' => $this->password]);
        $this->owner->assignPackage($packageKolab);
        $this->owner->setSettings($this->profile);

        $this->users[] = $this->getTestUser('jack@test.domain', ['password' => $this->password]);
        $this->users[] = $this->getTestUser('jane@test.domain', ['password' => $this->password]);
        $this->users[] = $this->getTestUser('jill@test.domain', ['password' => $this->password]);
        $this->users[] = $this->getTestUser('joe@test.domain', ['password' => $this->password]);

        foreach ($this->users as $user) {
            $this->owner->assignPackage($packageKolab, $user);
        }

        $this->users[] = $this->owner;

        usort(
            $this->users,
            function ($a, $b) {
                return $a->email > $b->email;
            }
        );

        $this->domain->assignPackage(\App\Package::where('title', 'domain-hosting')->first(), $this->owner);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            if ($user == $this->owner) {
                continue;
            }

            $this->deleteTestUser($user->email);
        }

        $this->deleteTestUser('john@test.domain');
        $this->deleteTestDomain('test.domain');

        parent::tearDown();
    }

    /**
     * Verify that a user page requires authentication.
     */
    public function testUserPageRequiresAuth(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit('/user/' . $this->owner->id)->on(new Home());
            }
        );
    }

    /**
     * VErify that the page with a list of users requires authentication
     */
    public function testUserListPageRequiresAuthentication(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit('/users')->on(new Home());
            }
        );
    }

    /**
     * Test users list page
     */
    public function testUsersListPageAsOwner(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit(new Home());
                $browser->submitLogon($this->owner->email, $this->password, true);
                $browser->on(new Dashboard());
                $browser->assertSeeIn('@links .link-users', 'User accounts');
                $browser->click('@links .link-users');
                $browser->on(new UserList());
                $browser->whenAvailable(
                    '@table',
                    function (Browser $browser) {
                        $browser->waitFor('tbody tr');
                        $browser->assertElementsCount('tbody tr', sizeof($this->users));

                        foreach ($this->users as $user) {
                            $arrayPosition = array_search($user, $this->users);
                            $listPosition = $arrayPosition + 1;

                            $browser->assertSeeIn("tbody tr:nth-child({$listPosition}) a", $user->email);
                            $browser->assertVisible("tbody tr:nth-child({$listPosition}) button.button-delete");
                        }

                        $browser->assertMissing('tfoot');
                    }
                );
            }
        );
    }

    /**
     * Test user account editing page (not profile page)
     *
     * @depends testUsersListPageAsOwner
     */
    public function testUserInfoPageAsOwner(): void
    {
        $this->browse(
            function (Browser $browser) {
                $browser->on(new UserList());
                $browser->click('@table tr:nth-child(' . (array_search($this->owner, $this->users) + 1) . ') a');
                $browser->on(new UserInfo());
                $browser->assertSeeIn('#user-info .card-title', 'User account');
                $browser->with(
                    '@form',
                    function (Browser $browser) {
                        // Assert form content
                        $browser->assertSeeIn('div.row:nth-child(1) label', 'Status');
                        $browser->assertSeeIn('div.row:nth-child(1) #status', 'Active');
                        $browser->assertFocused('div.row:nth-child(2) input');
                        $browser->assertSeeIn('div.row:nth-child(2) label', 'First name');
                        $browser->assertValue('div.row:nth-child(2) input[type=text]', $this->profile['first_name']);
                        $browser->assertSeeIn('div.row:nth-child(3) label', 'Last name');
                        $browser->assertValue('div.row:nth-child(3) input[type=text]', $this->profile['last_name']);
                        $browser->assertSeeIn('div.row:nth-child(4) label', 'Organization');
                        $browser->assertValue('div.row:nth-child(4) input[type=text]', $this->profile['organization']);
                        $browser->assertSeeIn('div.row:nth-child(5) label', 'Email');
                        $browser->assertValue('div.row:nth-child(5) input[type=text]', 'john@kolab.org');
                        $browser->assertDisabled('div.row:nth-child(5) input[type=text]');
                        $browser->assertSeeIn('div.row:nth-child(6) label', 'Email aliases');
                        $browser->assertVisible('div.row:nth-child(6) .list-input');

                        $browser->with(
                            new ListInput('#aliases'),
                            function (Browser $browser) {
                                $browser->assertListInputValue(['john.doe@' . $this->domain->namespace])
                                    ->assertValue('@input', '');
                            }
                        );

                        $browser->assertSeeIn('div.row:nth-child(7) label', 'Password');
                        $browser->assertValue('div.row:nth-child(7) input[type=password]', '');
                        $browser->assertSeeIn('div.row:nth-child(8) label', 'Confirm password');
                        $browser->assertValue('div.row:nth-child(8) input[type=password]', '');
                        $browser->assertSeeIn('button[type=submit]', 'Submit');

                        // Clear some fields and submit
                        $browser->vueClear('#first_name');
                        $browser->vueClear('#last_name');
                        $browser->click('button[type=submit]');
                    }
                );
                $browser->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
                $browser->on(new UserList());
                $browser->click('@table tr:nth-child(3) a');
                $browser->on(new UserInfo());
                $browser->assertSeeIn('#user-info .card-title', 'User account');
                $browser->with(
                    '@form',
                    function (Browser $browser) {
                        // Test error handling (password)
                        $browser->type('#password', 'aaaaaa');
                        $browser->vueClear('#password_confirmation');
                        $browser->click('button[type=submit]');
                        $browser->waitFor('#password + .invalid-feedback');
                        $browser->assertSeeIn(
                            '#password + .invalid-feedback',
                            'The password confirmation does not match.'
                        );

                        $browser->assertFocused('#password');
                        $browser->assertToast(Toast::TYPE_ERROR, 'Form validation error');

                        // TODO: Test password change

                        // Test form error handling (aliases)
                        $browser->vueClear('#password');
                        $browser->vueClear('#password_confirmation');

                        $browser->with(
                            new ListInput('#aliases'),
                            function (Browser $browser) {
                                $browser->addListEntry('invalid address');
                            }
                        );

                        $browser->click('button[type=submit]');
                        $browser->assertToast(Toast::TYPE_ERROR, 'Form validation error');

                        $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertFormError(2, 'The specified alias is invalid.', false);
                        });

                        // Test adding aliases
                        $browser->with(
                            new ListInput('#aliases'),
                            function (Browser $browser) {
                                $browser->removeListEntry(2);
                                $browser->addListEntry('john.test@' . $this->domain->namespace);
                            }
                        );

                        $browser->click('button[type=submit]');
                        $browser->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
                    }
                );

                $browser->on(new UserList());
                $browser->click('@table tr:nth-child(' . (array_search($this->owner, $this->users) + 1) . ') a');
                $browser->on(new UserInfo());

                $alias = $this->owner->aliases();
                $this->assertTrue(!empty($alias));

                // Test subscriptions
                $browser->with(
                    '@form',
                    function (Browser $browser) {
                        $browser->assertSeeIn('div.row:nth-child(9) label', 'Subscriptions');
                        $browser->assertVisible('@skus.row:nth-child(9)');
                        $browser->with(
                            '@skus',
                            function ($browser) {
                                $browser->assertElementsCount('tbody tr', 5);
                                // Mailbox SKU
                                $browser->assertSeeIn('tbody tr:nth-child(1) td.name', 'User Mailbox');
                                $browser->assertSeeIn('tbody tr:nth-child(1) td.price', '4,44 CHF/month');
                                $browser->assertChecked('tbody tr:nth-child(1) td.selection input');
                                $browser->assertDisabled('tbody tr:nth-child(1) td.selection input');
                                $browser->assertTip(
                                    'tbody tr:nth-child(1) td.buttons button',
                                    'Just a mailbox'
                                );

                                // Storage SKU
                                $browser->assertSeeIn('tbody tr:nth-child(2) td.name', 'Storage Quota');
                                $browser->assertSeeIn('tr:nth-child(2) td.price', '0,00 CHF/month');
                                $browser->assertChecked('tbody tr:nth-child(2) td.selection input');
                                $browser->assertDisabled('tbody tr:nth-child(2) td.selection input');
                                $browser->assertTip(
                                    'tbody tr:nth-child(2) td.buttons button',
                                    'Some wiggle room'
                                );

                                $browser->with(
                                    new QuotaInput('tbody tr:nth-child(2) .range-input'),
                                    function ($browser) {
                                        $browser->assertQuotaValue(2)->setQuotaValue(3);
                                    }
                                );

                                $browser->assertSeeIn('tr:nth-child(2) td.price', '0,25 CHF/month');

                                // groupware SKU
                                $browser->assertSeeIn('tbody tr:nth-child(3) td.name', 'Groupware Features');
                                $browser->assertSeeIn('tbody tr:nth-child(3) td.price', '5,55 CHF/month');
                                $browser->assertChecked('tbody tr:nth-child(3) td.selection input');
                                $browser->assertEnabled('tbody tr:nth-child(3) td.selection input');
                                $browser->assertTip(
                                    'tbody tr:nth-child(3) td.buttons button',
                                    'Groupware functions like Calendar, Tasks, Notes, etc.'
                                );

                                // ActiveSync SKU
                                $browser->assertSeeIn('tbody tr:nth-child(4) td.name', 'Activesync');
                                $browser->assertSeeIn('tbody tr:nth-child(4) td.price', '1,00 CHF/month');
                                $browser->assertNotChecked('tbody tr:nth-child(4) td.selection input');
                                $browser->assertEnabled('tbody tr:nth-child(4) td.selection input');
                                $browser->assertTip(
                                    'tbody tr:nth-child(4) td.buttons button',
                                    'Mobile synchronization'
                                );

                                // 2FA SKU
                                $browser->assertSeeIn('tbody tr:nth-child(5) td.name', '2-Factor Authentication');
                                $browser->assertSeeIn('tbody tr:nth-child(5) td.price', '0,00 CHF/month');
                                $browser->assertNotChecked('tbody tr:nth-child(5) td.selection input');
                                $browser->assertEnabled('tbody tr:nth-child(5) td.selection input');
                                $browser->assertTip(
                                    'tbody tr:nth-child(5) td.buttons button',
                                    'Two factor authentication for webmail and administration panel'
                                );

                                $browser->click('tbody tr:nth-child(4) td.selection input');
                            }
                        );

                        $browser->assertMissing('@skus table + .hint');
                        $browser->click('button[type=submit]');
                        $browser->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
                    }
                );

                $browser->on(new UserList());
                $browser->click('@table tr:nth-child(' (array_search($this->owner, $this->users) + 1) . ') a');
                $browser->on(new UserInfo());

                $expected = ['activesync', 'groupware', 'mailbox', 'storage', 'storage', 'storage'];
                $this->assertUserEntitlements($john, $expected);

                // Test subscriptions interaction
                $browser->with(
                    '@form',
                    function (Browser $browser) {
                        $browser->with(
                            '@skus',
                            function ($browser) {
                                // Uncheck 'groupware', expect activesync unchecked
                                $browser->click('#sku-input-groupware');
                                $browser->assertNotChecked('#sku-input-groupware');
                                $browser->assertNotChecked('#sku-input-activesync');
                                $browser->assertEnabled('#sku-input-activesync');
                                $browser->assertNotReadonly('#sku-input-activesync');

                                // Check 'activesync', expect an alert
                                $browser->click('#sku-input-activesync');
                                $browser->assertDialogOpened('Activesync requires Groupware Features.');
                                $browser->acceptDialog();
                                $browser->assertNotChecked('#sku-input-activesync');

                                // Check '2FA', expect 'activesync' unchecked and readonly
                                $browser->click('#sku-input-2fa');
                                $browser->assertChecked('#sku-input-2fa');
                                $browser->assertNotChecked('#sku-input-activesync');
                                $browser->assertReadonly('#sku-input-activesync');

                                // Uncheck '2FA'
                                $browser->click('#sku-input-2fa');
                                $browser->assertNotChecked('#sku-input-2fa');
                                $browser->assertNotReadonly('#sku-input-activesync');
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Test user adding page
     *
     * @depends testUsersListPageAsOwner
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
                        ->assertSeeIn('div.row:nth-child(3) label', 'Organization')
                        ->assertValue('div.row:nth-child(3) input[type=text]', '')
                        ->assertSeeIn('div.row:nth-child(4) label', 'Email')
                        ->assertValue('div.row:nth-child(4) input[type=text]', '')
                        ->assertEnabled('div.row:nth-child(4) input[type=text]')
                        ->assertSeeIn('div.row:nth-child(5) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(5) .list-input')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue([])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(6) label', 'Password')
                        ->assertValue('div.row:nth-child(6) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(7) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(7) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(8) label', 'Package')
                        // assert packages list widget, select "Lite Account"
                        ->with('@packages', function ($browser) {
                            $browser->assertElementsCount('tbody tr', 2)
                                ->assertSeeIn('tbody tr:nth-child(1)', 'Groupware Account')
                                ->assertSeeIn('tbody tr:nth-child(2)', 'Lite Account')
                                ->assertSeeIn('tbody tr:nth-child(1) .price', '9,99 CHF/month')
                                ->assertSeeIn('tbody tr:nth-child(2) .price', '4,44 CHF/month')
                                ->assertChecked('tbody tr:nth-child(1) input')
                                ->click('tbody tr:nth-child(2) input')
                                ->assertNotChecked('tbody tr:nth-child(1) input')
                                ->assertChecked('tbody tr:nth-child(2) input');
                        })
                        ->assertMissing('@packages table + .hint')
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
                        ->click('button[type=submit]')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->assertSeeIn('#email + .invalid-feedback', 'The specified email is invalid.')
                        ->assertSeeIn('#password + .invalid-feedback', 'The password confirmation does not match.');
                });

            // Test form error handling (aliases)
            $browser->with('@form', function (Browser $browser) {
                $browser->type('#email', 'julia.roberts@kolab.org')
                    ->type('#password_confirmation', 'simple123')
                    ->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->addListEntry('invalid address');
                    })
                    ->click('button[type=submit]')
                    ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                    ->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->assertFormError(1, 'The specified alias is invalid.', false);
                    });
            });

            // Successful account creation
            $browser->with(
                '@form',
                function (Browser $browser) {
                    $browser->type('#first_name', 'Julia');
                    $browser->type('#last_name', 'Roberts');
                    $browser->type('#organization', 'Test Org');
                    $browser->with(
                        new ListInput('#aliases'),
                        function (Browser $browser) {
                            $browser->removeListEntry(1)->addListEntry('julia.roberts2@kolab.org');
                        }
                    );

                    $browser->click('button[type=submit]');
                }
            );

            $browser->assertToast(Toast::TYPE_SUCCESS, 'User created successfully.');

            // check redirection to users list
            $browser->on(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 5)
                        ->assertSeeIn('tbody tr:nth-child(4) a', 'julia.roberts@kolab.org');
                });

            $julia = User::where('email', 'julia.roberts@kolab.org')->first();
            $alias = UserAlias::where('user_id', $julia->id)->where('alias', 'julia.roberts2@kolab.org')->first();
            $this->assertTrue(!empty($alias));
            $this->assertUserEntitlements($julia, ['mailbox', 'storage', 'storage']);
            $this->assertSame('Julia', $julia->getSetting('first_name'));
            $this->assertSame('Roberts', $julia->getSetting('last_name'));
            $this->assertSame('Test Org', $julia->getSetting('organization'));

            // Some additional tests for the list input widget
            $browser->click('tbody tr:nth-child(4) a')
                ->on(new UserInfo())
                ->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->assertListInputValue(['julia.roberts2@kolab.org'])
                        ->addListEntry('invalid address')
                        ->type('.input-group:nth-child(2) input', '@kolab.org');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                ->with(new ListInput('#aliases'), function (Browser $browser) {
                    $browser->assertVisible('.input-group:nth-child(2) input.is-invalid')
                        ->assertVisible('.input-group:nth-child(3) input.is-invalid')
                        ->type('.input-group:nth-child(2) input', 'julia.roberts3@kolab.org')
                        ->type('.input-group:nth-child(3) input', 'julia.roberts4@kolab.org');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');

            $julia = User::where('email', 'julia.roberts@kolab.org')->first();
            $aliases = $julia->aliases()->orderBy('alias')->get()->pluck('alias')->all();
            $this->assertSame(['julia.roberts3@kolab.org', 'julia.roberts4@kolab.org'], $aliases);
        });
    }

    /**
     * Test user delete
     *
     * @depends testNewUser
     */
    public function testDeleteUser(): void
    {
        // First create a new user
        $john = $this->getTestUser('john@kolab.org');
        $julia = $this->getTestUser('julia.roberts@kolab.org');
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $john->assignPackage($package_kolab, $julia);

        // Test deleting non-controller user
        $this->browse(function (Browser $browser) {
            $browser->visit(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 5)
                        ->assertSeeIn('tbody tr:nth-child(4) a', 'julia.roberts@kolab.org')
                        ->click('tbody tr:nth-child(4) button.button-delete');
                })
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Delete julia.roberts@kolab.org')
                        ->assertFocused('@button-cancel')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Delete')
                        ->click('@button-cancel');
                })
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->click('tbody tr:nth-child(4) button.button-delete');
                })
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User deleted successfully.')
                ->with('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'jack@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'joe@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'john@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(4) a', 'ned@kolab.org');
                });

            $julia = User::where('email', 'julia.roberts@kolab.org')->first();
            $this->assertTrue(empty($julia));

            // Test clicking Delete on the controller record redirects to /profile/delete
            $browser
                ->with('@table', function (Browser $browser) {
                    $browser->click('tbody tr:nth-child(3) button.button-delete');
                })
                ->waitForLocation('/profile/delete');
        });

        // Test that non-controller user cannot see/delete himself on the users list
        // Note: Access to /profile/delete page is tested in UserProfileTest.php
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->on(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->visit(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 0)
                        ->assertSeeIn('tfoot td', 'There are no users in this account.');
                });
        });

        // Test that controller user (Ned) can see/delete all the users ???
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->on(new Home())
                ->submitLogon('ned@kolab.org', 'simple123', true)
                ->visit(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertElementsCount('tbody button.button-delete', 4);
                });

                // TODO: Test the delete action in details
        });

        // TODO: Test what happens with the logged in user session after he's been deleted by another user
    }

    /**
     * Test discounted sku/package prices in the UI
     */
    public function testDiscountedPrices(): void
    {
        // Add 10% discount
        $discount = Discount::where('code', 'TEST')->first();
        $john = User::where('email', 'john@kolab.org')->first();
        $wallet = $john->wallet();
        $wallet->discount()->associate($discount);
        $wallet->save();

        // SKUs on user edit page
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->visit(new UserList())
                ->waitFor('@table tr:nth-child(2)')
                ->click('@table tr:nth-child(2) a')
                ->on(new UserInfo())
                ->with('@form', function (Browser $browser) {
                    $browser->whenAvailable('@skus', function (Browser $browser) {
                        $quota_input = new QuotaInput('tbody tr:nth-child(2) .range-input');
                        $browser->waitFor('tbody tr')
                            ->assertElementsCount('tbody tr', 5)
                            // Mailbox SKU
                            ->assertSeeIn('tbody tr:nth-child(1) td.price', '3,99 CHF/month¹')
                            // Storage SKU
                            ->assertSeeIn('tr:nth-child(2) td.price', '0,00 CHF/month¹')
                            ->with($quota_input, function (Browser $browser) {
                                $browser->setQuotaValue(100);
                            })
                            ->assertSeeIn('tr:nth-child(2) td.price', '21,56 CHF/month¹')
                            // groupware SKU
                            ->assertSeeIn('tbody tr:nth-child(3) td.price', '4,99 CHF/month¹')
                            // ActiveSync SKU
                            ->assertSeeIn('tbody tr:nth-child(4) td.price', '0,90 CHF/month¹')
                            // 2FA SKU
                            ->assertSeeIn('tbody tr:nth-child(5) td.price', '0,00 CHF/month¹');
                    })
                    ->assertSeeIn('@skus table + .hint', '¹ applied discount: 10% - Test voucher');
                });
        });

        // Packages on new user page
        $this->browse(function (Browser $browser) {
            $browser->visit(new UserList())
                ->click('button.create-user')
                ->on(new UserInfo())
                ->with('@form', function (Browser $browser) {
                    $browser->whenAvailable('@packages', function (Browser $browser) {
                        $browser->assertElementsCount('tbody tr', 2)
                            ->assertSeeIn('tbody tr:nth-child(1) .price', '8,99 CHF/month¹') // Groupware
                            ->assertSeeIn('tbody tr:nth-child(2) .price', '3,99 CHF/month¹'); // Lite
                    })
                    ->assertSeeIn('@packages table + .hint', '¹ applied discount: 10% - Test voucher');
                });
        });
    }
}
