<?php

namespace Tests\Browser;

use App\Group;
use App\Sku;
use Tests\Browser;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\Status;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\DistlistInfo;
use Tests\Browser\Pages\DistlistList;
use Tests\TestCaseDusk;

class DistlistTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group-test@kolab.org');
        $this->clearBetaEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolab.org');
        $this->clearBetaEntitlements();

        parent::tearDown();
    }

    /**
     * Test distlist info page (unauthenticated)
     */
    public function testInfoUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/distlist/abc')->on(new Home());
        });
    }

    /**
     * Test distlist list page (unauthenticated)
     */
    public function testListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/distlists')->on(new Home());
        });
    }

    /**
     * Test distlist list page
     */
    public function testList(): void
    {
        // Log on the user
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertMissing('@links .link-distlists');
        });

        // Test that Distribution lists page is not accessible without the 'distlist' entitlement
        $this->browse(function (Browser $browser) {
            $browser->visit('/distlists')
                ->assertErrorPage(404);
        });

        // Create a single group, add beta+distlist entitlements
        $john = $this->getTestUser('john@kolab.org');
        $this->addDistlistEntitlement($john);
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        // Test distribution lists page
        $this->browse(function (Browser $browser) {
            $browser->visit(new Dashboard())
                ->assertSeeIn('@links .link-distlists', 'Distribution lists')
                ->click('@links .link-distlists')
                ->on(new DistlistList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertElementsCount('tbody tr', 1)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'group-test@kolab.org')
                        ->assertText('tbody tr:nth-child(1) svg.text-danger title', 'Not Ready')
                        ->assertMissing('tfoot');
                });
        });
    }

    /**
     * Test distlist creation/editing/deleting
     *
     * @depends testList
     */
    public function testCreateUpdateDelete(): void
    {
        // Test that the page is not available accessible without the 'distlist' entitlement
        $this->browse(function (Browser $browser) {
            $browser->visit('/distlist/new')
                ->assertErrorPage(404);
        });

        // Add beta+distlist entitlements
        $john = $this->getTestUser('john@kolab.org');
        $this->addDistlistEntitlement($john);

        $this->browse(function (Browser $browser) {
            // Create a group
            $browser->visit(new DistlistList())
                ->assertSeeIn('button.create-list', 'Create list')
                ->click('button.create-list')
                ->on(new DistlistInfo())
                ->assertSeeIn('#distlist-info .card-title', 'New distribution list')
                ->with('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertMissing('#status')
                        ->assertSeeIn('div.row:nth-child(1) label', 'Email')
                        ->assertValue('div.row:nth-child(1) input[type=text]', '')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Recipients')
                        ->assertVisible('div.row:nth-child(2) .list-input')
                        ->with(new ListInput('#members'), function (Browser $browser) {
                            $browser->assertListInputValue([])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('button[type=submit]', 'Submit');
                })
                // Test error conditions
                ->type('#email', 'group-test@kolabnow.com')
                ->click('button[type=submit]')
                ->waitFor('#email + .invalid-feedback')
                ->assertSeeIn('#email + .invalid-feedback', 'The specified domain is not available.')
                ->assertFocused('#email')
                ->waitFor('#members + .invalid-feedback')
                ->assertSeeIn('#members + .invalid-feedback', 'At least one recipient is required.')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                // Test successful group creation
                ->type('#email', 'group-test@kolab.org')
                ->with(new ListInput('#members'), function (Browser $browser) {
                    $browser->addListEntry('test1@gmail.com')
                        ->addListEntry('test2@gmail.com');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Distribution list created successfully.')
                ->on(new DistlistList())
                ->assertElementsCount('@table tbody tr', 1);

            // Test group update
            $browser->click('@table tr:nth-child(1) a')
                ->on(new DistlistInfo())
                ->assertSeeIn('#distlist-info .card-title', 'Distribution list')
                ->with('@form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Status')
                        ->assertSeeIn('div.row:nth-child(1) span.text-danger', 'Not Ready')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Email')
                        ->assertValue('div.row:nth-child(2) input[type=text]:disabled', 'group-test@kolab.org')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Recipients')
                        ->assertVisible('div.row:nth-child(3) .list-input')
                        ->with(new ListInput('#members'), function (Browser $browser) {
                            $browser->assertListInputValue(['test1@gmail.com', 'test2@gmail.com'])
                                ->assertValue('@input', '');
                        })
                        ->assertSeeIn('button[type=submit]', 'Submit');
                })
                // Test error handling
                ->with(new ListInput('#members'), function (Browser $browser) {
                    $browser->addListEntry('invalid address');
                })
                ->click('button[type=submit]')
                ->waitFor('#members + .invalid-feedback')
                ->assertSeeIn('#members + .invalid-feedback', 'The specified email address is invalid.')
                ->assertVisible('#members .input-group:nth-child(4) input.is-invalid')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                // Test successful update
                ->with(new ListInput('#members'), function (Browser $browser) {
                    $browser->removeListEntry(3)->removeListEntry(2);
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Distribution list updated successfully.')
                ->assertMissing('.invalid-feedback')
                ->on(new DistlistList())
                ->assertElementsCount('@table tbody tr', 1);

            $group = Group::where('email', 'group-test@kolab.org')->first();
            $this->assertSame(['test1@gmail.com'], $group->members);

            // Test group deletion
            $browser->click('@table tr:nth-child(1) a')
                ->on(new DistlistInfo())
                ->assertSeeIn('button.button-delete', 'Delete list')
                ->click('button.button-delete')
                ->assertToast(Toast::TYPE_SUCCESS, 'Distribution list deleted successfully.')
                ->on(new DistlistList())
                ->assertElementsCount('@table tbody tr', 0)
                ->assertVisible('@table tfoot');

            $this->assertNull(Group::where('email', 'group-test@kolab.org')->first());
        });
    }

    /**
     * Test distribution list status
     *
     * @depends testList
     */
    public function testStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $this->addDistlistEntitlement($john);
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());
        $group->status = Group::STATUS_NEW | Group::STATUS_ACTIVE;
        $group->save();

        $this->assertFalse($group->isLdapReady());

        $this->browse(function ($browser) use ($group) {
            // Test auto-refresh
            $browser->visit('/distlist/' . $group->id)
                ->on(new DistlistInfo())
                ->with(new Status(), function ($browser) {
                    $browser->assertSeeIn('@body', 'We are preparing the distribution list')
                        ->assertProgress(83, 'Creating a distribution list...', 'pending')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-link')
                        ->assertMissing('#status-verify');
                });

            $group->status |= Group::STATUS_LDAP_READY;
            $group->save();

            // Test Verify button
            $browser->waitUntilMissing('@status', 10);
        });

        // TODO: Test all group statuses on the list
    }


    /**
     * Register the beta + distlist entitlements for the user
     */
    private function addDistlistEntitlement($user): void
    {
        // Add beta+distlist entitlements
        $beta_sku = Sku::where('title', 'beta')->first();
        $distlist_sku = Sku::where('title', 'distlist')->first();
        $user->assignSku($beta_sku);
        $user->assignSku($distlist_sku);
    }
}