<?php

namespace Tests\Browser\Admin;

use App\Auth\SecondFactor;
use App\Discount;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
                'phone' => '+48123123123',
                'external_email' => 'john.doe.external@gmail.com',
        ]);
        if ($john->isSuspended()) {
            User::where('email', $john->email)->update(['status' => $john->status - User::STATUS_SUSPENDED]);
        }
        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();

        Entitlement::where('cost', '>=', 5000)->delete();
        $this->deleteTestGroup('group-test@kolab.org');
        $this->clearMeetEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
                'phone' => null,
                'external_email' => 'john.doe.external@gmail.com',
        ]);
        if ($john->isSuspended()) {
            User::where('email', $john->email)->update(['status' => $john->status - User::STATUS_SUSPENDED]);
        }
        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->save();

        Entitlement::where('cost', '>=', 5000)->delete();
        $this->deleteTestGroup('group-test@kolab.org');
        $this->clearMeetEntitlements();

        parent::tearDown();
    }

    /**
     * Test user info page (unauthenticated)
     */
    public function testUserUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $jack = $this->getTestUser('jack@kolab.org');
            $browser->visit('/user/' . $jack->id)->on(new Home());
        });
    }

    /**
     * Test user info page
     */
    public function testUserInfo(): void
    {
        $this->browse(function (Browser $browser) {
            $jack = $this->getTestUser('jack@kolab.org');
            $page = new UserPage($jack->id);

            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', \App\Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->visit($page)
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $jack->email)
                ->with('@user-info form', function (Browser $browser) use ($jack) {
                    $browser->assertElementsCount('.row', 7)
                        ->assertSeeIn('.row:nth-child(1) label', 'Managed by')
                        ->assertSeeIn('.row:nth-child(1) #manager a', 'john@kolab.org')
                        ->assertSeeIn('.row:nth-child(2) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(2) #userid', "{$jack->id} ({$jack->created_at})")
                        ->assertSeeIn('.row:nth-child(3) label', 'Status')
                        ->assertSeeIn('.row:nth-child(3) #status span.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(4) label', 'First Name')
                        ->assertSeeIn('.row:nth-child(4) #first_name', 'Jack')
                        ->assertSeeIn('.row:nth-child(5) label', 'Last Name')
                        ->assertSeeIn('.row:nth-child(5) #last_name', 'Daniels')
                        ->assertSeeIn('.row:nth-child(6) label', 'External Email')
                        ->assertMissing('.row:nth-child(6) #external_email a')
                        ->assertSeeIn('.row:nth-child(7) label', 'Country')
                        ->assertSeeIn('.row:nth-child(7) #country', 'United States');
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 9);

            // Note: Finances tab is tested in UserFinancesTest.php
            $browser->assertSeeIn('@nav #tab-finances', 'Finances');

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (1)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 1)
                        ->assertSeeIn('table tbody tr:first-child td:first-child', 'jack.daniels@kolab.org')
                        ->assertMissing('table tfoot');
                });

            // Assert Subscriptions tab
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (3)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 3)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '5,00 CHF')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 5 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,90 CHF')
                        ->assertMissing('table tfoot')
                        ->assertMissing('#reset2fa');
                });

            // Assert Domains tab
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (0)')
                ->click('@nav #tab-domains')
                ->with('@user-domains', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no domains in this account.');
                });

            // Assert Users tab
            $browser->assertSeeIn('@nav #tab-users', 'Users (0)')
                ->click('@nav #tab-users')
                ->with('@user-users', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no users in this account.');
                });

            // Assert Distribution lists tab
            $browser->assertSeeIn('@nav #tab-distlists', 'Distribution lists (0)')
                ->click('@nav #tab-distlists')
                ->with('@user-distlists', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no distribution lists in this account.');
                });

            // Assert Resources tab
            $browser->assertSeeIn('@nav #tab-resources', 'Resources (0)')
                ->click('@nav #tab-resources')
                ->with('@user-resources', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no resources in this account.');
                });

            // Assert Shared folders tab
            $browser->assertSeeIn('@nav #tab-shared-folders', 'Shared folders (0)')
                ->click('@nav #tab-shared-folders')
                ->with('@user-shared-folders', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no shared folders in this account.');
                });

            // Assert Settings tab
            $browser->assertSeeIn('@nav #tab-settings', 'Settings')
                ->click('@nav #tab-settings')
                ->whenAvailable('@user-settings form', function (Browser $browser) {
                    $browser->assertElementsCount('.row', 1)
                        ->assertSeeIn('.row:first-child label', 'Greylisting')
                        ->assertSeeIn('.row:first-child .text-success', 'enabled');
                });
        });
    }

    /**
     * Test user info page (continue)
     *
     * @depends testUserInfo
     */
    public function testUserInfo2(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');
            $page = new UserPage($john->id);
            $discount = Discount::where('code', 'TEST')->first();
            $wallet = $john->wallet();
            $wallet->discount()->associate($discount);
            $wallet->debit(2010);
            $wallet->save();
            $group = $this->getTestGroup('group-test@kolab.org', ['name' => 'Test Group']);
            $group->assignToWallet($john->wallets->first());
            $john->setSetting('greylist_enabled', null);

            // Click the managed-by link on Jack's page
            $browser->click('@user-info #manager a')
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $john->email)
                ->with('@user-info form', function (Browser $browser) use ($john) {
                    $ext_email = $john->getSetting('external_email');

                    $browser->assertElementsCount('.row', 9)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(1) #userid', "{$john->id} ({$john->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status span.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(3) label', 'First Name')
                        ->assertSeeIn('.row:nth-child(3) #first_name', 'John')
                        ->assertSeeIn('.row:nth-child(4) label', 'Last Name')
                        ->assertSeeIn('.row:nth-child(4) #last_name', 'Doe')
                        ->assertSeeIn('.row:nth-child(5) label', 'Organization')
                        ->assertSeeIn('.row:nth-child(5) #organization', 'Kolab Developers')
                        ->assertSeeIn('.row:nth-child(6) label', 'Phone')
                        ->assertSeeIn('.row:nth-child(6) #phone', $john->getSetting('phone'))
                        ->assertSeeIn('.row:nth-child(7) label', 'External Email')
                        ->assertSeeIn('.row:nth-child(7) #external_email a', $ext_email)
                        ->assertAttribute('.row:nth-child(7) #external_email a', 'href', "mailto:$ext_email")
                        ->assertSeeIn('.row:nth-child(8) label', 'Address')
                        ->assertSeeIn('.row:nth-child(8) #billing_address', $john->getSetting('billing_address'))
                        ->assertSeeIn('.row:nth-child(9) label', 'Country')
                        ->assertSeeIn('.row:nth-child(9) #country', 'United States');
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 9);

            // Note: Finances tab is tested in UserFinancesTest.php
            $browser->assertSeeIn('@nav #tab-finances', 'Finances');

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (1)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 1)
                        ->assertSeeIn('table tbody tr:first-child td:first-child', 'john.doe@kolab.org')
                        ->assertMissing('table tfoot');
                });

            // Assert Subscriptions tab
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (3)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 3)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '4,50 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 5 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,41 CHF/month¹')
                        ->assertMissing('table tfoot')
                        ->assertSeeIn('table + .hint', '¹ applied discount: 10% - Test voucher');
                });

            // Assert Domains tab
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (1)')
                ->click('@nav #tab-domains')
                ->with('@user-domains table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertSeeIn('tbody tr:nth-child(1) td:first-child a', 'kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-success')
                        ->assertMissing('tfoot');
                });

            // Assert Users tab
            $browser->assertSeeIn('@nav #tab-users', 'Users (4)')
                ->click('@nav #tab-users')
                ->with('@user-users table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(1) td:first-child a', 'jack@kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-success')
                        ->assertSeeIn('tbody tr:nth-child(2) td:first-child a', 'joe@kolab.org')
                        ->assertVisible('tbody tr:nth-child(2) td:first-child svg.text-success')
                        ->assertSeeIn('tbody tr:nth-child(3) td:first-child span', 'john@kolab.org')
                        ->assertVisible('tbody tr:nth-child(3) td:first-child svg.text-success')
                        ->assertSeeIn('tbody tr:nth-child(4) td:first-child a', 'ned@kolab.org')
                        ->assertVisible('tbody tr:nth-child(4) td:first-child svg.text-success')
                        ->assertMissing('tfoot');
                });

            // Assert Distribution lists tab
            $browser->assertSeeIn('@nav #tab-distlists', 'Distribution lists (1)')
                ->click('@nav #tab-distlists')
                ->with('@user-distlists table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertSeeIn('tbody tr:nth-child(1) td:first-child a', 'Test Group')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-danger')
                        ->assertSeeIn('tbody tr:nth-child(1) td:last-child a', 'group-test@kolab.org')
                        ->assertMissing('tfoot');
                });

            // Assert Resources tab
            $browser->assertSeeIn('@nav #tab-resources', 'Resources (2)')
                ->click('@nav #tab-resources')
                ->with('@user-resources', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 2)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'Conference Room #1')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', 'resource-test1@kolab.org')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Conference Room #2')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', 'resource-test2@kolab.org')
                        ->assertMissing('table tfoot');
                });

            // Assert Shared folders tab
            $browser->assertSeeIn('@nav #tab-shared-folders', 'Shared folders (2)')
                ->click('@nav #tab-shared-folders')
                ->with('@user-shared-folders', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 2)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'Calendar')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:nth-child(2)', 'Calendar')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', 'folder-event@kolab.org')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Contacts')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:nth-child(2)', 'Address Book')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', 'folder-contact@kolab.org')
                        ->assertMissing('table tfoot');
                });
        });

        // Now we go to Ned's info page, he's a controller on John's wallet
        $this->browse(function (Browser $browser) {
            $ned = $this->getTestUser('ned@kolab.org');
            $beta_sku = Sku::withEnvTenantContext()->where('title', 'beta')->first();
            $storage_sku = Sku::withEnvTenantContext()->where('title', 'storage')->first();
            $wallet = $ned->wallet();

            // Add an extra storage and beta entitlement with different prices
            Entitlement::create([
                    'wallet_id' => $wallet->id,
                    'sku_id' => $beta_sku->id,
                    'cost' => 5010,
                    'entitleable_id' => $ned->id,
                    'entitleable_type' => User::class
            ]);
            Entitlement::create([
                    'wallet_id' => $wallet->id,
                    'sku_id' => $storage_sku->id,
                    'cost' => 5000,
                    'entitleable_id' => $ned->id,
                    'entitleable_type' => User::class
            ]);

            $page = new UserPage($ned->id);
            $ned->setSetting('greylist_enabled', 'false');

            $browser->click('@nav #tab-users')
                ->click('@user-users tbody tr:nth-child(4) td:first-child a')
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $ned->email)
                ->with('@user-info form', function (Browser $browser) use ($ned) {
                    $browser->assertSeeIn('.row:nth-child(2) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(2) #userid', "{$ned->id} ({$ned->created_at})");
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 9);

            // Note: Finances tab is tested in UserFinancesTest.php
            $browser->assertSeeIn('@nav #tab-finances', 'Finances');

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (0)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'This user has no email aliases.');
                });

            // Assert Subscriptions tab, we expect John's discount here
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (6)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 6)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '4,50 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 6 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '45,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,41 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(4) td:first-child', 'Activesync')
                        ->assertSeeIn('table tbody tr:nth-child(4) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(5) td:first-child', '2-Factor Authentication')
                        ->assertSeeIn('table tbody tr:nth-child(5) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(6) td:first-child', 'Private Beta (invitation only)')
                        ->assertSeeIn('table tbody tr:nth-child(6) td:last-child', '45,09 CHF/month¹')
                        ->assertMissing('table tfoot')
                        ->assertSeeIn('table + .hint', '¹ applied discount: 10% - Test voucher')
                        ->assertSeeIn('#reset2fa', 'Reset 2-Factor Auth')
                        ->assertMissing('#addbetasku');
                });

            // We don't expect John's domains here
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (0)')
                ->click('@nav #tab-domains')
                ->with('@user-domains', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no domains in this account.');
                });

            // We don't expect John's users here
            $browser->assertSeeIn('@nav #tab-users', 'Users (0)')
                ->click('@nav #tab-users')
                ->with('@user-users', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no users in this account.');
                });

            // We don't expect John's distribution lists here
            $browser->assertSeeIn('@nav #tab-distlists', 'Distribution lists (0)')
                ->click('@nav #tab-distlists')
                ->with('@user-distlists', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no distribution lists in this account.');
                });

            // We don't expect John's resources here
            $browser->assertSeeIn('@nav #tab-resources', 'Resources (0)')
                ->click('@nav #tab-resources')
                ->with('@user-resources', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no resources in this account.');
                });

            // We don't expect John's folders here
            $browser->assertSeeIn('@nav #tab-shared-folders', 'Shared folders (0)')
                ->click('@nav #tab-shared-folders')
                ->with('@user-shared-folders', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no shared folders in this account.');
                });

            // Assert Settings tab
            $browser->assertSeeIn('@nav #tab-settings', 'Settings')
                ->click('@nav #tab-settings')
                ->whenAvailable('@user-settings form', function (Browser $browser) {
                    $browser->assertElementsCount('.row', 1)
                        ->assertSeeIn('.row:first-child label', 'Greylisting')
                        ->assertSeeIn('.row:first-child .text-danger', 'disabled');
                });
        });
    }

    /**
     * Test editing an external email
     *
     * @depends testUserInfo2
     */
    public function testExternalEmail(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');

            $browser->visit(new UserPage($john->id))
                ->waitFor('@user-info #external_email button')
                ->click('@user-info #external_email button')
                // Test dialog content, and closing it with Cancel button
                ->with(new Dialog('#email-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'External Email')
                        ->assertFocused('@body input')
                        ->assertValue('@body input', 'john.doe.external@gmail.com')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Submit')
                        ->click('@button-cancel');
                })
                ->assertMissing('#email-dialog')
                ->click('@user-info #external_email button')
                // Test email validation error handling, and email update
                ->with(new Dialog('#email-dialog'), function (Browser $browser) {
                    $browser->type('@body input', 'test')
                        ->click('@button-action')
                        ->waitFor('@body input.is-invalid')
                        ->assertSeeIn(
                            '@body input + .invalid-feedback',
                            'The external email must be a valid email address.'
                        )
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->type('@body input', 'test@test.com')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.')
                ->assertSeeIn('@user-info #external_email a', 'test@test.com')
                ->click('@user-info #external_email button')
                ->with(new Dialog('#email-dialog'), function (Browser $browser) {
                    $browser->assertValue('@body input', 'test@test.com')
                        ->assertMissing('@body input.is-invalid')
                        ->assertMissing('@body input + .invalid-feedback')
                        ->click('@button-cancel');
                })
                ->assertSeeIn('@user-info #external_email a', 'test@test.com');

            // $john->getSetting() may not work here as it uses internal cache
            // read the value form database
            $current_ext_email = $john->settings()->where('key', 'external_email')->first()->value;
            $this->assertSame('test@test.com', $current_ext_email);
        });
    }

    /**
     * Test suspending/unsuspending the user
     */
    public function testSuspendAndUnsuspend(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');

            $browser->visit(new UserPage($john->id))
                ->assertVisible('@user-info #button-suspend')
                ->assertMissing('@user-info #button-unsuspend')
                ->click('@user-info #button-suspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'User suspended successfully.')
                ->assertSeeIn('@user-info #status span.text-warning', 'Suspended')
                ->assertMissing('@user-info #button-suspend')
                ->click('@user-info #button-unsuspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'User unsuspended successfully.')
                ->assertSeeIn('@user-info #status span.text-success', 'Active')
                ->assertVisible('@user-info #button-suspend')
                ->assertMissing('@user-info #button-unsuspend');
        });
    }

    /**
     * Test resetting 2FA for the user
     */
    public function testReset2FA(): void
    {
        $this->browse(function (Browser $browser) {
            $this->deleteTestUser('userstest1@kolabnow.com');
            $user = $this->getTestUser('userstest1@kolabnow.com');
            $sku2fa = Sku::withEnvTenantContext()->where('title', '2fa')->first();
            $user->assignSku($sku2fa);
            SecondFactor::seed('userstest1@kolabnow.com');

            $browser->visit(new UserPage($user->id))
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) use ($sku2fa) {
                    $browser->waitFor('#reset2fa')
                        ->assertVisible('#sku' . $sku2fa->id);
                })
                ->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (1)')
                ->click('#reset2fa')
                ->with(new Dialog('#reset-2fa-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', '2-Factor Authentication Reset')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Reset')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, '2-Factor authentication reset successfully.')
                ->assertMissing('#sku' . $sku2fa->id)
                ->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (0)');
        });
    }

    /**
     * Test adding the beta SKU for the user
     */
    public function testAddBetaSku(): void
    {
        $this->browse(function (Browser $browser) {
            $this->deleteTestUser('userstest1@kolabnow.com');
            $user = $this->getTestUser('userstest1@kolabnow.com');
            $sku = Sku::withEnvTenantContext()->where('title', 'beta')->first();

            $browser->visit(new UserPage($user->id))
                ->click('@nav #tab-subscriptions')
                ->waitFor('@user-subscriptions #addbetasku')
                ->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (0)')
                ->assertSeeIn('#addbetasku', 'Enable beta program')
                ->click('#addbetasku')
                ->assertToast(Toast::TYPE_SUCCESS, 'The subscription added successfully.')
                ->waitFor('#sku' . $sku->id)
                ->assertSeeIn("#sku{$sku->id} td:first-child", 'Private Beta (invitation only)')
                ->assertSeeIn("#sku{$sku->id} td:last-child", '0,00 CHF/month')
                ->assertMissing('#addbetasku')
                ->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (1)');
        });
    }
}
