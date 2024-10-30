<?php

namespace Tests\Browser;

use App\Domain;
use App\User;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\DomainInfo;
use Tests\Browser\Pages\DomainList;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class DomainTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->deleteTestDomain('testdomain.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestDomain('testdomain.com');
        parent::tearDown();
    }

    /**
     * Test domain info page (unauthenticated)
     */
    public function testDomainInfoUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function ($browser) {
            $browser->visit('/domain/123')->on(new Home());
        });
    }

    /**
     * Test domains list page (unauthenticated)
     */
    public function testDomainListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function ($browser) {
            $browser->visit('/domains')->on(new Home());
        });
    }

    /**
     * Test domain info page (non-existing domain id)
     * @group skipci
     */
    public function testDomainInfo404(): void
    {
        $this->browse(function ($browser) {
            // FIXME: I couldn't make loginAs() method working

            // Note: Here we're also testing that unauthenticated request
            //       is passed to logon form and then "redirected" to the requested page
            $browser->visit('/domain/123')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123')
                ->assertErrorPage(404);
        });
    }

    /**
     * Test domain info page (existing domain)
     *
     * @depends testDomainInfo404
     * @group skipci
     */
    public function testDomainInfo(): void
    {
        $this->browse(function ($browser) {
            // Unconfirmed domain
            $domain = Domain::where('namespace', 'kolab.org')->first();
            if ($domain->isConfirmed()) {
                $domain->status ^= Domain::STATUS_CONFIRMED;
                $domain->save();
            }

            $domain->setSetting('spf_whitelist', \json_encode(['.test.com']));

            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainInfo())
                ->assertSeeIn('.card-title', 'Domain')
                ->whenAvailable('@general', function ($browser) use ($domain) {
                    $browser->assertSeeIn('form div:nth-child(1) label', 'Status')
                        ->assertSeeIn('form div:nth-child(1) #status.text-danger', 'Not Ready')
                        ->assertSeeIn('form div:nth-child(2) label', 'Name')
                        ->assertValue('form div:nth-child(2) input:disabled', $domain->namespace)
                        ->assertSeeIn('form div:nth-child(3) label', 'Subscriptions');
                })
                ->whenAvailable('@general form div:nth-child(3) table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertVisible('tbody tr td.selection input:checked:disabled')
                        ->assertSeeIn('tbody tr td.name', 'External Domain')
                        ->assertSeeIn('tbody tr td.price', '0,00 CHF/month')
                        ->assertTip(
                            'tbody tr td.buttons button',
                            'Host a domain that is externally registered'
                        );
                })
                ->whenAvailable('@confirm', function ($browser) use ($domain) {
                    $browser->assertSeeIn('pre', $domain->namespace)
                        ->assertSeeIn('pre', $domain->hash())
                        ->scrollTo('button')->pause(500)
                        ->click('button')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Domain ownership confirmed successfully.');

                        // TODO: Test scenario when a domain confirmation failed
                })
                ->whenAvailable('@config', function ($browser) use ($domain) {
                    $browser->assertSeeIn('pre', $domain->namespace);
                })
                ->assertMissing('@general button[type=submit]')
                ->assertMissing('@confirm');

            // Check that confirmed domain page contains only the config box
            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainInfo())
                ->assertMissing('@confirm')
                ->assertPresent('@config');
        });
    }

    /**
     * Test domain settings
     * @group skipci
     */
    public function testDomainSettings(): void
    {
        $this->browse(function ($browser) {
            $domain = Domain::where('namespace', 'kolab.org')->first();
            $domain->setSetting('spf_whitelist', \json_encode(['.test.com']));

            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainInfo())
                ->assertElementsCount('@nav a', 2)
                ->assertSeeIn('@nav #tab-general', 'General')
                ->assertSeeIn('@nav #tab-settings', 'Settings')
                ->click('@nav #tab-settings')
                ->with('#settings form', function (Browser $browser) {
                    // Test whitelist widget
                    $widget = new ListInput('#spf_whitelist');

                    $browser->assertSeeIn('div.row:nth-child(1) label', 'SPF Whitelist')
                        ->assertVisible('div.row:nth-child(1) .list-input')
                        ->with($widget, function (Browser $browser) {
                            $browser->assertListInputValue(['.test.com'])
                                ->assertValue('@input', '')
                                ->addListEntry('invalid domain');
                        })
                        ->click('button[type=submit]')
                        ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                        ->with($widget, function (Browser $browser) {
                            $err = 'The entry format is invalid. Expected a domain name starting with a dot.';
                            $browser->assertFormError(2, $err, false)
                                ->removeListEntry(2)
                                ->removeListEntry(1)
                                ->addListEntry('.new.domain.tld');
                        })
                        ->click('button[type=submit]')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Domain settings updated successfully.');
                });
        });
    }

    /**
     * Test domains list page
     *
     * @depends testDomainListUnauth
     * @group skipci
     */
    public function testDomainList(): void
    {
        $this->browse(function ($browser) {
            // Login the user
            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                // On dashboard click the "Domains" link
                ->on(new Dashboard())
                ->assertSeeIn('@links a.link-domains', 'Domains')
                ->click('@links a.link-domains')
                // On Domains List page click the domain entry
                ->on(new DomainList())
                ->waitFor('@table tbody tr')
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-globe.text-success')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Active')
                ->assertSeeIn('@table tbody tr:first-child td:first-child', 'kolab.org')
                ->assertMissing('@table tfoot')
                ->click('@table tbody tr:first-child td:first-child a')
                // On Domain Info page verify that's the clicked domain
                ->on(new DomainInfo())
                ->whenAvailable('@config', function ($browser) {
                    $browser->assertSeeIn('pre', 'kolab.org');
                });
        });

        // TODO: Test domains list acting as Ned (John's "delegatee")
    }

    /**
     * Test domains list page (user with no domains)
     */
    public function testDomainListEmpty(): void
    {
        $this->browse(function ($browser) {
            // Login the user
            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertVisible('@links a.link-settings')
                ->assertMissing('@links a.link-domains')
                ->assertMissing('@links a.link-users')
                ->assertMissing('@links a.link-wallet');
/*
                // On dashboard click the "Domains" link
                ->assertSeeIn('@links a.link-domains', 'Domains')
                ->click('@links a.link-domains')
                // On Domains List page click the domain entry
                ->on(new DomainList())
                ->assertMissing('@table tbody')
                ->assertSeeIn('tfoot td', 'There are no domains in this account.');
*/
        });
    }

    /**
     * Test domain creation page
     * @group skipci
     */
    public function testDomainCreate(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->visit('/domains')
                ->on(new DomainList())
                ->assertSeeIn('.card-title button.btn-success', 'Create domain')
                ->click('.card-title button.btn-success')
                ->on(new DomainInfo())
                ->assertSeeIn('.card-title', 'New domain')
                ->assertElementsCount('@nav li', 1)
                ->assertSeeIn('@nav li:first-child', 'General')
                ->whenAvailable('@general', function ($browser) {
                    $browser->assertSeeIn('form div:nth-child(1) label', 'Name')
                        ->assertValue('form div:nth-child(1) input:not(:disabled)', '')
                        ->assertFocused('form div:nth-child(1) input')
                        ->assertSeeIn('form div:nth-child(2) label', 'Package')
                        ->assertMissing('form div:nth-child(3)');
                })
                ->whenAvailable('@general form div:nth-child(2) table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertVisible('tbody tr td.selection input:checked[readonly]')
                        ->assertSeeIn('tbody tr td.name', 'Domain Hosting')
                        ->assertSeeIn('tbody tr td.price', '0,00 CHF/month')
                        ->assertTip(
                            'tbody tr td.buttons button',
                            'Use your own, existing domain.'
                        );
                })
                ->assertSeeIn('@general button.btn-primary[type=submit]', 'Submit')
                ->assertMissing('@config')
                ->assertMissing('@confirm')
                ->assertMissing('@settings')
                ->assertMissing('@status')
                // Test error handling
                ->click('button[type=submit]')
                ->waitFor('#namespace + .invalid-feedback')
                ->assertSeeIn('#namespace + .invalid-feedback', 'The namespace field is required.')
                ->assertFocused('#namespace')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                ->type('@general form div:nth-child(1) input', 'testdomain..com')
                ->click('button[type=submit]')
                ->waitFor('#namespace + .invalid-feedback')
                ->assertSeeIn('#namespace + .invalid-feedback', 'The specified domain is invalid.')
                ->assertFocused('#namespace')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error')
                // Test success
                ->type('@general form div:nth-child(1) input', 'testdomain.com')
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'Domain created successfully.')
                ->on(new DomainList())
                ->assertSeeIn('@table tr:nth-child(2) a', 'testdomain.com');
        });
    }

    /**
     * Test domain deletion
     * @group skipci
     */
    public function testDomainDelete(): void
    {
        // Create the domain to delete
        $john = $this->getTestUser('john@kolab.org');
        $domain = $this->getTestDomain('testdomain.com', ['type' => Domain::TYPE_EXTERNAL]);
        $packageDomain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $domain->assignPackage($packageDomain, $john);

        $this->browse(function ($browser) {
            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123')
                ->visit('/domains')
                ->on(new DomainList())
                ->assertElementsCount('@table tbody tr', 2)
                ->assertSeeIn('@table tr:nth-child(2) a', 'testdomain.com')
                ->click('@table tbody tr:nth-child(2) a')
                ->on(new DomainInfo())
                ->waitFor('button.button-delete')
                ->assertSeeIn('button.button-delete', 'Delete domain')
                ->click('button.button-delete')
                ->with(new Dialog('#delete-warning'), function ($browser) {
                    $browser->assertSeeIn('@title', 'Delete testdomain.com')
                        ->assertFocused('@button-cancel')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Delete')
                        ->click('@button-cancel');
                })
                ->waitUntilMissing('#delete-warning')
                ->click('button.button-delete')
                ->with(new Dialog('#delete-warning'), function (Browser $browser) {
                    $browser->click('@button-action');
                })
                ->waitUntilMissing('#delete-warning')
                ->assertToast(Toast::TYPE_SUCCESS, 'Domain deleted successfully.')
                ->on(new DomainList())
                ->assertElementsCount('@table tbody tr', 1);

            // Test error handling on deleting a non-empty domain
            $err = 'Unable to delete a domain with assigned users or other objects.';
            $browser->click('@table tbody tr:nth-child(1) a')
                ->on(new DomainInfo())
                ->waitFor('button.button-delete')
                ->click('button.button-delete')
                ->with(new Dialog('#delete-warning'), function ($browser) {
                    $browser->click('@button-action');
                })
                ->assertToast(Toast::TYPE_ERROR, $err);
        });
    }
}
