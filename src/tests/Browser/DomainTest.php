<?php

namespace Tests\Browser;

use App\Domain;
use App\User;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\DomainInfo;
use Tests\Browser\Pages\DomainList;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DomainTest extends TestCaseDusk
{

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
     * Test domain info page (non-existing domain id)
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
     */
    public function testDomainInfo(): void
    {
        $this->browse(function ($browser) {
            // Unconfirmed domain
            $domain = Domain::where('namespace', 'kolab.org')->first();
            $domain->status ^= Domain::STATUS_CONFIRMED;
            $domain->save();

            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainInfo())
                ->whenAvailable('@verify', function ($browser) use ($domain) {
                    $browser->assertSeeIn('pre', $domain->namespace)
                        ->assertSeeIn('pre', $domain->hash())
                        ->click('button')
                        ->assertToast(Toast::TYPE_ERROR, 'Domain ownership verification failed.');

                    // Make sure the domain is confirmed now
                    $domain->status |= Domain::STATUS_CONFIRMED;
                    $domain->save();

                    $browser->click('button')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Domain verified successfully.');
                })
                ->whenAvailable('@config', function ($browser) use ($domain) {
                    $browser->assertSeeIn('pre', $domain->namespace);
                })
                ->assertMissing('@verify');

            // Check that confirmed domain page contains only the config box
            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainInfo())
                ->assertMissing('@verify')
                ->assertPresent('@config');
        });
    }

    /**
     * Test domains list page (unauthenticated)
     */
    public function testDomainListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function ($browser) {
            $browser->visit('/logout')
                ->visit('/domains')
                ->on(new Home());
        });
    }

    /**
     * Test domains list page
     *
     * @depends testDomainListUnauth
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
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-globe.text-success')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Active')
                ->assertSeeIn('@table tbody tr:first-child td:first-child', 'kolab.org')
                ->click('@table tbody tr:first-child td:first-child a')
                // On Domain Info page verify that's the clicked domain
                ->on(new DomainInfo())
                ->whenAvailable('@config', function ($browser) {
                    $browser->assertSeeIn('pre', 'kolab.org');
                });
        });

        // TODO: Test domains list acting as Ned (John's "delegatee")
    }
}
