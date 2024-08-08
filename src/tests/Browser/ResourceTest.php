<?php

namespace Tests\Browser;

use App\Resource;
use Tests\Browser;
use Tests\Browser\Components\Status;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\ResourceInfo;
use Tests\Browser\Pages\ResourceList;
use Tests\TestCaseDusk;

class ResourceTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clearBetaEntitlements();
        Resource::whereNotIn('email', ['resource-test1@kolab.org', 'resource-test2@kolab.org'])->delete();
        // Remove leftover entitlements that might interfere with the tests
        \App\Entitlement::where('entitleable_type', 'App\\Resource')
            ->whereRaw('entitleable_id not in (select id from resources where deleted_at is null)')
            ->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \App\Sku::withEnvTenantContext()->where('title', 'resource')->update(['units_free' => 0]);
        Resource::whereNotIn('email', ['resource-test1@kolab.org', 'resource-test2@kolab.org'])->delete();
        $this->clearBetaEntitlements();

        parent::tearDown();
    }

    /**
     * Test resource info page (unauthenticated)
     */
    public function testInfoUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/resource/abc')->on(new Home());
        });
    }

    /**
     * Test resource list page (unauthenticated)
     */
    public function testListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/resources')->on(new Home());
        });
    }

    /**
     * Test resources list page
     */
    public function testList(): void
    {
        // Log on the user
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertMissing('@links .link-resources');
        });

        // Test that Resources lists page is not accessible without the 'beta' entitlement
        $this->browse(function (Browser $browser) {
            $browser->visit('/resources')
                ->assertErrorPage(403);
        });

        // Add beta entitlements
        $john = $this->getTestUser('john@kolab.org');
        $this->addBetaEntitlement($john);
        // Make sure the first resource is active
        $resource = $this->getTestResource('resource-test1@kolab.org');
        $resource->status = Resource::STATUS_NEW | Resource::STATUS_ACTIVE
            | Resource::STATUS_LDAP_READY | Resource::STATUS_IMAP_READY;
        $resource->save();

        // Test resources lists page
        $this->browse(function (Browser $browser) {
            $browser->visit(new Dashboard())
                ->assertSeeIn('@links .link-resources', 'Resources')
                ->click('@links .link-resources')
                ->on(new ResourceList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertSeeIn('thead tr th:nth-child(1)', 'Name')
                        ->assertSeeIn('thead tr th:nth-child(2)', 'Email Address')
                        ->assertElementsCount('tbody tr', 2)
                        ->assertSeeIn('tbody tr:nth-child(1) td:nth-child(1) a', 'Conference Room #1')
                        ->assertText('tbody tr:nth-child(1) td:nth-child(1) svg.text-success title', 'Active')
                        ->assertSeeIn('tbody tr:nth-child(1) td:nth-child(2) a', 'kolab.org')
                        ->assertMissing('tfoot');
                });
        });
    }

    /**
     * Test resource creation/editing/deleting
     *
     * @depends testList
     */
    public function testCreateUpdateDelete(): void
    {
        // Test that the page is not available accessible without the 'beta' entitlement
        $this->browse(function (Browser $browser) {
            $browser->visit('/resource/new')
                ->assertErrorPage(403);
        });

        // Add beta entitlement
        $john = $this->getTestUser('john@kolab.org');
        $this->addBetaEntitlement($john);

        \App\Sku::withEnvTenantContext()->where('title', 'resource')->update(['units_free' => 3]);

        $this->browse(function (Browser $browser) {
            // Create a resource
            $browser->visit(new ResourceList())
                ->assertSeeIn('button.resource-new', 'Create resource')
                ->click('button.resource-new')
                ->on(new ResourceInfo())
                ->assertSeeIn('#resource-info .card-title', 'New resource')
                ->assertSeeIn('@nav #tab-general', 'General')
                ->assertMissing('@nav #tab-settings')
                ->with('@general', function (Browser $browser) {
                    // Assert form content
                    $browser->assertMissing('#status')
                        ->assertFocused('#name')
                        ->assertSeeIn('div.row:nth-child(1) label', 'Name')
                        ->assertValue('div.row:nth-child(1) input[type=text]', '')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Domain')
                        ->assertSelectHasOptions('div.row:nth-child(2) select', ['kolab.org'])
                        ->assertValue('div.row:nth-child(2) select', 'kolab.org')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Subscriptions')
                        ->with('@skus', function ($browser) {
                            $browser->assertElementsCount('tbody tr', 1)
                                ->assertSeeIn('tbody tr:nth-child(1) td.name', 'Resource')
                                ->assertSeeIn('tbody tr:nth-child(1) td.price', '0,00 CHF/month') // one free unit left
                                ->assertChecked('tbody tr:nth-child(1) td.selection input')
                                ->assertDisabled('tbody tr:nth-child(1) td.selection input')
                                ->assertTip(
                                    'tbody tr:nth-child(1) td.buttons button',
                                    'Reservation taker'
                                );
                        })
                        ->assertSeeIn('button[type=submit]', 'Submit');
                })
                // Test error conditions
                ->type('#name', str_repeat('A', 192))
                ->click('@general button[type=submit]')
                ->waitFor('#name + .invalid-feedback')
                ->assertSeeIn('#name + .invalid-feedback', 'The name may not be greater than 191 characters.')
                ->assertFocused('#name')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                // Test successful resource creation
                ->type('#name', 'Test Resource')
                ->click('@general button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Resource created successfully.')
                ->on(new ResourceList())
                ->assertElementsCount('@table tbody tr', 3);

            // Test resource update
            $browser->click('@table tr:nth-child(3) td:first-child a')
                ->on(new ResourceInfo())
                ->assertSeeIn('#resource-info .card-title', 'Resource')
                ->with('@general', function (Browser $browser) {
                    // Assert form content
                    $browser->assertFocused('#name')
                        ->assertSeeIn('div.row:nth-child(1) label', 'Status')
                        ->assertSeeIn('div.row:nth-child(1) span.text-danger', 'Not Ready')
                        ->assertSeeIn('div.row:nth-child(2) label', 'Name')
                        ->assertValue('div.row:nth-child(2) input[type=text]', 'Test Resource')
                        ->assertSeeIn('div.row:nth-child(3) label', 'Email')
                        ->assertAttributeRegExp(
                            'div.row:nth-child(3) input[type=text]:disabled',
                            'value',
                            '/^resource-[0-9]+@kolab\.org$/'
                        )
                        ->with('@skus', function ($browser) {
                            $browser->assertElementsCount('tbody tr', 1)
                                ->assertSeeIn('tbody tr:nth-child(1) td.name', 'Resource')
                                ->assertSeeIn('tbody tr:nth-child(1) td.price', '0,00 CHF/month')
                                ->assertChecked('tbody tr:nth-child(1) td.selection input')
                                ->assertDisabled('tbody tr:nth-child(1) td.selection input')
                                ->assertTip(
                                    'tbody tr:nth-child(1) td.buttons button',
                                    'Reservation taker'
                                );
                        })
                        ->assertSeeIn('button[type=submit]', 'Submit');
                })
                // Test error handling
                ->type('#name', str_repeat('A', 192))
                ->click('@general button[type=submit]')
                ->waitFor('#name + .invalid-feedback')
                ->assertSeeIn('#name + .invalid-feedback', 'The name may not be greater than 191 characters.')
                ->assertVisible('#name.is-invalid')
                ->assertFocused('#name')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                // Test successful update
                ->type('#name', 'Test Resource Update')
                ->click('@general button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Resource updated successfully.')
                ->on(new ResourceList())
                ->assertElementsCount('@table tbody tr', 3)
                ->assertSeeIn('@table tr:nth-child(3) td:first-child a', 'Test Resource Update');

            $this->assertSame(1, Resource::where('name', 'Test Resource Update')->count());

            // Test resource deletion
            $browser->click('@table tr:nth-child(3) td:first-child a')
                ->on(new ResourceInfo())
                ->assertSeeIn('button.button-delete', 'Delete resource')
                ->click('button.button-delete')
                ->assertToast(Toast::TYPE_SUCCESS, 'Resource deleted successfully.')
                ->on(new ResourceList())
                ->assertElementsCount('@table tbody tr', 2);

            $this->assertNull(Resource::where('name', 'Test Resource Update')->first());
        });

        // Assert Subscription price for the case when there's no free units
        \App\Sku::withEnvTenantContext()->where('title', 'resource')->update(['units_free' => 2]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/resource/new')
                ->on(new ResourceInfo())
                ->with('@skus', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertSeeIn('tbody tr:nth-child(1) td.name', 'Resource')
                        ->assertSeeIn('tbody tr:nth-child(1) td.price', '1,01 CHF/month')
                        ->assertChecked('tbody tr:nth-child(1) td.selection input')
                        ->assertDisabled('tbody tr:nth-child(1) td.selection input');
                });
        });
    }

    /**
     * Test resource status
     *
     * @depends testList
     */
    public function testStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $this->addBetaEntitlement($john);
        $resource = $this->getTestResource('resource-test2@kolab.org');
        $resource->status = Resource::STATUS_NEW | Resource::STATUS_ACTIVE | Resource::STATUS_LDAP_READY;
        $resource->created_at = \now();
        $resource->save();

        $this->assertFalse($resource->isImapReady());

        $this->browse(function ($browser) use ($resource) {
            // Test auto-refresh
            $browser->visit('/resource/' . $resource->id)
                ->on(new ResourceInfo())
                ->with(new Status(), function ($browser) {
                    $browser->assertSeeIn('@body', 'We are preparing the resource')
                        ->assertProgress(\config('app.with_ldap') ? 85 : 80, 'Creating a shared folder...', 'pending')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-link')
                        ->assertMissing('#status-verify');
                });

            $resource->status |= Resource::STATUS_IMAP_READY;
            $resource->save();

            // Test Verify button
            $browser->waitUntilMissing('@status', 10);
        });

        // TODO: Test all resource statuses on the list
    }

    /**
     * Test resource settings
     */
    public function testSettings(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $this->addBetaEntitlement($john);
        $resource = $this->getTestResource('resource-test2@kolab.org');
        $resource->setSetting('invitation_policy', null);

        $this->browse(function ($browser) use ($resource) {
            // Test auto-refresh
            $browser->visit('/resource/' . $resource->id)
                ->on(new ResourceInfo())
                ->assertSeeIn('@nav #tab-general', 'General')
                ->assertSeeIn('@nav #tab-settings', 'Settings')
                ->click('@nav #tab-settings')
                ->with('@settings form', function (Browser $browser) {
                    // Assert form content
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Invitation policy')
                        ->assertSelectHasOptions('div.row:nth-child(1) select', ['accept', 'manual', 'reject'])
                        ->assertValue('div.row:nth-child(1) select', 'accept')
                        ->assertMissing('div.row:nth-child(1) input')
                        ->assertSeeIn('div.row:nth-child(1) small', 'manual acceptance')
                        ->assertSeeIn('button[type=submit]', 'Submit');
                })
                // Test error handling
                ->select('#invitation_policy', 'manual')
                ->waitFor('#invitation_policy + input')
                ->type('#invitation_policy + input', 'kolab.org')
                ->click('@settings button[type=submit]')
                ->waitFor('#invitation_policy + input + .invalid-feedback')
                ->assertSeeIn(
                    '#invitation_policy + input + .invalid-feedback',
                    'The specified email address is invalid.'
                )
                ->assertVisible('#invitation_policy + input.is-invalid')
                ->assertFocused('#invitation_policy + input')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                ->type('#invitation_policy + input', 'jack@kolab.org')
                ->click('@settings button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Resource settings updated successfully.')
                ->assertMissing('.invalid-feedback')
                ->refresh()
                ->on(new ResourceInfo())
                ->click('@nav #tab-settings')
                ->with('@settings form', function (Browser $browser) {
                    $browser->assertValue('div.row:nth-child(1) select', 'manual')
                        ->assertVisible('div.row:nth-child(1) input')
                        ->assertValue('div.row:nth-child(1) input', 'jack@kolab.org');
                });
        });
    }
}
