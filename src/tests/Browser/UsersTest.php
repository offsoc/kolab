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
    private $profile = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'organization' => 'Kolab Developers',
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('julia.roberts@kolab.org');

        $john = User::where('email', 'john@kolab.org')->first();
        $john->setSettings($this->profile);
        UserAlias::where('user_id', $john->id)
            ->where('alias', 'john.test@kolab.org')->delete();

        Entitlement::where('entitleable_id', $john->id)->whereIn('cost', [25, 100])->delete();

        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('julia.roberts@kolab.org');

        $john = User::where('email', 'john@kolab.org')->first();
        $john->setSettings($this->profile);
        UserAlias::where('user_id', $john->id)
            ->where('alias', 'john.test@kolab.org')->delete();

        Entitlement::where('entitleable_id', $john->id)->whereIn('cost', [25, 100])->delete();

        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();

        $this->clearBetaEntitlements();
        $this->clearMeetEntitlements();

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
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'jack@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'joe@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'john@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(4) a', 'ned@kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) button.button-delete')
                        ->assertVisible('tbody tr:nth-child(2) button.button-delete')
                        ->assertVisible('tbody tr:nth-child(3) button.button-delete')
                        ->assertVisible('tbody tr:nth-child(4) button.button-delete')
                        ->assertMissing('tfoot');
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
                ->click('@table tr:nth-child(3) a')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info .card-title', 'User account')
                ->with('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Status')
                        ->assertSeeIn('div.row:nth-child(1) #status', 'Active')
                        ->assertFocused('div.row:nth-child(2) input')
                        ->assertSeeIn('div.row:nth-child(2) label', 'First name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', $this->profile['first_name'])
                        ->assertSeeIn('div.row:nth-child(3) label', 'Last name')
                        ->assertValue('div.row:nth-child(3) input[type=text]', $this->profile['last_name'])
                        ->assertSeeIn('div.row:nth-child(4) label', 'Organization')
                        ->assertValue('div.row:nth-child(4) input[type=text]', $this->profile['organization'])
                        ->assertSeeIn('div.row:nth-child(5) label', 'Email')
                        ->assertValue('div.row:nth-child(5) input[type=text]', 'john@kolab.org')
                        ->assertDisabled('div.row:nth-child(5) input[type=text]')
                        ->assertSeeIn('div.row:nth-child(6) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(6) .list-input')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue(['john.doe@kolab.org'])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(7) label', 'Password')
                        ->assertValue('div.row:nth-child(7) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(8) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(8) input[type=password]', '')
                        ->assertSeeIn('button[type=submit]', 'Submit')
                        // Clear some fields and submit
                        ->vueClear('#first_name')
                        ->vueClear('#last_name')
                        ->click('button[type=submit]');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.')
                ->on(new UserList())
                ->click('@table tr:nth-child(3) a')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info .card-title', 'User account')
                ->with('@form', function (Browser $browser) {
                    // Test error handling (password)
                    $browser->type('#password', 'aaaaaa')
                        ->vueClear('#password_confirmation')
                        ->click('button[type=submit]')
                        ->waitFor('#password + .invalid-feedback')
                        ->assertSeeIn('#password + .invalid-feedback', 'The password confirmation does not match.')
                        ->assertFocused('#password')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error');

                    // TODO: Test password change

                    // Test form error handling (aliases)
                    $browser->vueClear('#password')
                        ->vueClear('#password_confirmation')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->addListEntry('invalid address');
                        })
                        ->click('button[type=submit]')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error');

                    $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->assertFormError(2, 'The specified alias is invalid.', false);
                    });

                    // Test adding aliases
                    $browser->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->removeListEntry(2)
                            ->addListEntry('john.test@kolab.org');
                    })
                        ->click('button[type=submit]')
                        ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
                })
                ->on(new UserList())
                ->click('@table tr:nth-child(3) a')
                ->on(new UserInfo());

            $john = User::where('email', 'john@kolab.org')->first();
            $alias = UserAlias::where('user_id', $john->id)->where('alias', 'john.test@kolab.org')->first();
            $this->assertTrue(!empty($alias));

            // Test subscriptions
            $browser->with('@form', function (Browser $browser) {
                $browser->assertSeeIn('div.row:nth-child(9) label', 'Subscriptions')
                    ->assertVisible('@skus.row:nth-child(9)')
                    ->with('@skus', function ($browser) {
                        $browser->assertElementsCount('tbody tr', 6)
                            // Mailbox SKU
                            ->assertSeeIn('tbody tr:nth-child(1) td.name', 'User Mailbox')
                            ->assertSeeIn('tbody tr:nth-child(1) td.price', '4,44 CHF/month')
                            ->assertChecked('tbody tr:nth-child(1) td.selection input')
                            ->assertDisabled('tbody tr:nth-child(1) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(1) td.buttons button',
                                'Just a mailbox'
                            )
                            // Storage SKU
                            ->assertSeeIn('tbody tr:nth-child(2) td.name', 'Storage Quota')
                            ->assertSeeIn('tr:nth-child(2) td.price', '0,00 CHF/month')
                            ->assertChecked('tbody tr:nth-child(2) td.selection input')
                            ->assertDisabled('tbody tr:nth-child(2) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(2) td.buttons button',
                                'Some wiggle room'
                            )
                            ->with(new QuotaInput('tbody tr:nth-child(2) .range-input'), function ($browser) {
                                $browser->assertQuotaValue(2)->setQuotaValue(3);
                            })
                            ->assertSeeIn('tr:nth-child(2) td.price', '0,25 CHF/month')
                            // groupware SKU
                            ->assertSeeIn('tbody tr:nth-child(3) td.name', 'Groupware Features')
                            ->assertSeeIn('tbody tr:nth-child(3) td.price', '5,55 CHF/month')
                            ->assertChecked('tbody tr:nth-child(3) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(3) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(3) td.buttons button',
                                'Groupware functions like Calendar, Tasks, Notes, etc.'
                            )
                            // ActiveSync SKU
                            ->assertSeeIn('tbody tr:nth-child(4) td.name', 'Activesync')
                            ->assertSeeIn('tbody tr:nth-child(4) td.price', '1,00 CHF/month')
                            ->assertNotChecked('tbody tr:nth-child(4) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(4) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(4) td.buttons button',
                                'Mobile synchronization'
                            )
                            // 2FA SKU
                            ->assertSeeIn('tbody tr:nth-child(5) td.name', '2-Factor Authentication')
                            ->assertSeeIn('tbody tr:nth-child(5) td.price', '0,00 CHF/month')
                            ->assertNotChecked('tbody tr:nth-child(5) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(5) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(5) td.buttons button',
                                'Two factor authentication for webmail and administration panel'
                            )
                            // Meet SKU
                            ->assertSeeIn('tbody tr:nth-child(6) td.name', 'Voice & Video Conferencing (public beta)')
                            ->assertSeeIn('tbody tr:nth-child(6) td.price', '0,00 CHF/month')
                            ->assertNotChecked('tbody tr:nth-child(6) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(6) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(6) td.buttons button',
                                'Video conferencing tool'
                            )
                            ->click('tbody tr:nth-child(4) td.selection input');
                    })
                    ->assertMissing('@skus table + .hint')
                    ->click('button[type=submit]')
                    ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');
            })
                ->on(new UserList())
                ->click('@table tr:nth-child(3) a')
                ->on(new UserInfo());

            $expected = ['activesync', 'groupware', 'mailbox', 'storage', 'storage', 'storage'];
            $this->assertUserEntitlements($john, $expected);

            // Test subscriptions interaction
            $browser->with('@form', function (Browser $browser) {
                $browser->with('@skus', function ($browser) {
                    // Uncheck 'groupware', expect activesync unchecked
                    $browser->click('#sku-input-groupware')
                        ->assertNotChecked('#sku-input-groupware')
                        ->assertNotChecked('#sku-input-activesync')
                        ->assertEnabled('#sku-input-activesync')
                        ->assertNotReadonly('#sku-input-activesync')
                        // Check 'activesync', expect an alert
                        ->click('#sku-input-activesync')
                        ->assertDialogOpened('Activesync requires Groupware Features.')
                        ->acceptDialog()
                        ->assertNotChecked('#sku-input-activesync')
                        // Check 'meet', expect an alert
                        ->click('#sku-input-meet')
                        ->assertDialogOpened('Voice & Video Conferencing (public beta) requires Groupware Features.')
                        ->acceptDialog()
                        ->assertNotChecked('#sku-input-meet')
                        // Check '2FA', expect 'activesync' unchecked and readonly
                        ->click('#sku-input-2fa')
                        ->assertChecked('#sku-input-2fa')
                        ->assertNotChecked('#sku-input-activesync')
                        ->assertReadonly('#sku-input-activesync')
                        // Uncheck '2FA'
                        ->click('#sku-input-2fa')
                        ->assertNotChecked('#sku-input-2fa')
                        ->assertNotReadonly('#sku-input-activesync');
                });
            });
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
            $browser->with('@form', function (Browser $browser) {
                $browser->type('#first_name', 'Julia')
                    ->type('#last_name', 'Roberts')
                    ->type('#organization', 'Test Org')
                    ->with(new ListInput('#aliases'), function (Browser $browser) {
                        $browser->removeListEntry(1)
                            ->addListEntry('julia.roberts2@kolab.org');
                    })
                    ->click('button[type=submit]');
            })
            ->assertToast(Toast::TYPE_SUCCESS, 'User created successfully.')
            // check redirection to users list
            ->on(new UserList())
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
                            ->assertElementsCount('tbody tr', 6)
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

    /**
     * Test beta entitlements
     *
     * @depends testList
     */
    public function testBetaEntitlements(): void
    {
        $this->browse(function (Browser $browser) {
            $john = User::where('email', 'john@kolab.org')->first();
            $sku = Sku::where('title', 'beta')->first();
            $john->assignSku($sku);

            $browser->visit('/user/' . $john->id)
                ->on(new UserInfo())
                ->with('@skus', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 7)
                        // Beta/Meet SKU
                        ->assertSeeIn('tbody tr:nth-child(6) td.name', 'Voice & Video Conferencing (public beta)')
                        ->assertSeeIn('tr:nth-child(6) td.price', '0,00 CHF/month')
                        ->assertNotChecked('tbody tr:nth-child(6) td.selection input')
                        ->assertEnabled('tbody tr:nth-child(6) td.selection input')
                        ->assertTip(
                            'tbody tr:nth-child(6) td.buttons button',
                            'Video conferencing tool'
                        )
                        // Beta SKU
                        ->assertSeeIn('tbody tr:nth-child(7) td.name', 'Private Beta (invitation only)')
                        ->assertSeeIn('tbody tr:nth-child(7) td.price', '0,00 CHF/month')
                        ->assertChecked('tbody tr:nth-child(7) td.selection input')
                        ->assertEnabled('tbody tr:nth-child(7) td.selection input')
                        ->assertTip(
                            'tbody tr:nth-child(7) td.buttons button',
                            'Access to the private beta program subscriptions'
                        )
/*
                        // Check Meet, Uncheck Beta, expect Meet unchecked
                        ->click('#sku-input-meet')
                        ->click('#sku-input-beta')
                        ->assertNotChecked('#sku-input-beta')
                        ->assertNotChecked('#sku-input-meet')
                        // Click Meet expect an alert
                        ->click('#sku-input-meet')
                        ->assertDialogOpened('Video chat requires Beta program.')
                        ->acceptDialog()
*/
                        // Enable Meet and submit
                        ->click('#sku-input-meet');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');

            $expected = ['beta', 'groupware', 'mailbox', 'meet', 'storage', 'storage'];
            $this->assertUserEntitlements($john, $expected);

            $browser->visit('/user/' . $john->id)
                ->on(new UserInfo())
                ->click('#sku-input-beta')
                ->click('#sku-input-meet')
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.');

            $expected = ['groupware', 'mailbox', 'storage', 'storage'];
            $this->assertUserEntitlements($john, $expected);
        });
    }
}
