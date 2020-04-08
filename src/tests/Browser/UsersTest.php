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
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'jack@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'john@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'ned@kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) button.button-delete')
                        ->assertVisible('tbody tr:nth-child(2) button.button-delete')
                        ->assertVisible('tbody tr:nth-child(3) button.button-delete');
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
                ->click('@table tr:nth-child(2) a')
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
                        ->assertSeeIn('div.row:nth-child(4) label', 'Email')
                        ->assertValue('div.row:nth-child(4) input[type=text]', 'john@kolab.org')
                        ->assertDisabled('div.row:nth-child(4) input[type=text]')
                        ->assertSeeIn('div.row:nth-child(5) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(5) .list-input')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue(['john.doe@kolab.org'])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(6) label', 'Password')
                        ->assertValue('div.row:nth-child(6) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(7) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(7) input[type=password]', '')
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

            // Test subscriptions
            $browser->with('@form', function (Browser $browser) {
                $browser->assertSeeIn('div.row:nth-child(8) label', 'Subscriptions')
                    ->assertVisible('@skus.row:nth-child(8)')
                    ->with('@skus', function ($browser) {
                        $browser->assertElementsCount('tbody tr', 5)
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
                            // 2FA SKU
                            ->assertSeeIn('tbody tr:nth-child(4) td.name', '2-Factor Authentication')
                            ->assertSeeIn('tbody tr:nth-child(4) td.price', '0,00 CHF/month')
                            ->assertNotChecked('tbody tr:nth-child(4) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(4) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(4) td.buttons button',
                                'Two factor authentication for webmail and administration panel'
                            )
                            // ActiveSync SKU
                            ->assertSeeIn('tbody tr:nth-child(5) td.name', 'Activesync')
                            ->assertSeeIn('tbody tr:nth-child(5) td.price', '1,00 CHF/month')
                            ->assertNotChecked('tbody tr:nth-child(5) td.selection input')
                            ->assertEnabled('tbody tr:nth-child(5) td.selection input')
                            ->assertTip(
                                'tbody tr:nth-child(5) td.buttons button',
                                'Mobile synchronization'
                            )
                            ->click('tbody tr:nth-child(5) td.selection input');
                    })
                    ->assertMissing('@skus table + .hint')
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('User data updated successfully')
                    ->closeToast();
            });

            $expected = ['activesync', 'groupware', 'mailbox', 'storage', 'storage', 'storage'];
            $this->assertUserEntitlements($john, $expected);

            // Test subscriptions interaction
            $browser->with('@form', function (Browser $browser) {
                $browser->with('@skus', function ($browser) {
                    // Uncheck 'groupware', expect activesync unchecked
                    $browser->click('@sku-input-groupware')
                        ->assertNotChecked('@sku-input-groupware')
                        ->assertNotChecked('@sku-input-activesync')
                        ->assertEnabled('@sku-input-activesync')
                        ->assertNotReadonly('@sku-input-activesync')
                        // Check 'activesync', expect an alert
                        ->click('@sku-input-activesync')
                        ->assertDialogOpened('Activesync requires Groupware Features.')
                        ->acceptDialog()
                        ->assertNotChecked('@sku-input-activesync')
                        // Check '2FA', expect 'activesync' unchecked and readonly
                        ->click('@sku-input-2fa')
                        ->assertChecked('@sku-input-2fa')
                        ->assertNotChecked('@sku-input-activesync')
                        ->assertReadonly('@sku-input-activesync')
                        // Uncheck '2FA'
                        ->click('@sku-input-2fa')
                        ->assertNotChecked('@sku-input-2fa')
                        ->assertNotReadonly('@sku-input-activesync');
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
                        ->assertSeeIn('div.row:nth-child(3) label', 'Email')
                        ->assertValue('div.row:nth-child(3) input[type=text]', '')
                        ->assertEnabled('div.row:nth-child(3) input[type=text]')
                        ->assertSeeIn('div.row:nth-child(4) label', 'Email aliases')
                        ->assertVisible('div.row:nth-child(4) .list-input')
                        ->with(new ListInput('#aliases'), function (Browser $browser) {
                            $browser->assertListInputValue([])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('div.row:nth-child(5) label', 'Password')
                        ->assertValue('div.row:nth-child(5) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(6) label', 'Confirm password')
                        ->assertValue('div.row:nth-child(6) input[type=password]', '')
                        ->assertSeeIn('div.row:nth-child(7) label', 'Package')
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
                $browser->type('#email', 'julia.roberts@kolab.org')
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
                        ->addListEntry('julia.roberts2@kolab.org');
                })
                    ->click('button[type=submit]');
            })
            ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                $browser->assertToastTitle('')
                    ->assertToastMessage('User created successfully')
                    ->closeToast();
            })
            // check redirection to users list
            ->waitForLocation('/users')
            ->on(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'julia.roberts@kolab.org');
                });

            $julia = User::where('email', 'julia.roberts@kolab.org')->first();
            $alias = UserAlias::where('user_id', $julia->id)->where('alias', 'julia.roberts2@kolab.org')->first();
            $this->assertTrue(!empty($alias));
            $this->assertUserEntitlements($julia, ['mailbox', 'storage', 'storage']);
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
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'julia.roberts@kolab.org')
                        ->click('tbody tr:nth-child(3) button.button-delete');
                })
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Delete julia.roberts@kolab.org')
                        ->assertFocused('@button-cancel')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Delete')
                        ->click('@button-cancel');
                })
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->click('tbody tr:nth-child(3) button.button-delete');
                })
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->click('@button-action');
                })
                ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                    $browser->assertToastTitle('')
                        ->assertToastMessage('User deleted successfully')
                        ->closeToast();
                })
                ->with('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 3)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'jack@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'john@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'ned@kolab.org');
                });

            $julia = User::where('email', 'julia.roberts@kolab.org')->first();
            $this->assertTrue(empty($julia));

            // Test clicking Delete on the controller record redirects to /profile/delete
            $browser
                ->with('@table', function (Browser $browser) {
                    $browser->click('tbody tr:nth-child(2) button.button-delete');
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
                    $browser->assertElementsCount('tbody tr', 0);
                });
        });

        // Test that controller user (Ned) can see/delete all the users ???
        $this->browse(function (Browser $browser) {
            $browser->visit('/logout')
                ->on(new Home())
                ->submitLogon('ned@kolab.org', 'simple123', true)
                ->visit(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 3)
                        ->assertElementsCount('tbody button.button-delete', 3);
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
                ->click('@table tr:nth-child(2) a')
                ->on(new UserInfo())
                ->with('@form', function (Browser $browser) {
                    $browser->whenAvailable('@skus', function (Browser $browser) {
                        $quota_input = new QuotaInput('tbody tr:nth-child(2) .range-input');
                        $browser->assertElementsCount('tbody tr', 5)
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
                            // 2FA SKU
                            ->assertSeeIn('tbody tr:nth-child(4) td.price', '0,00 CHF/month¹')
                            // ActiveSync SKU
                            ->assertSeeIn('tbody tr:nth-child(5) td.price', '0,90 CHF/month¹');
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
