<?php

namespace Tests\Browser;

use App\Domain;
use App\User;
use Tests\Browser\Components\Error;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Domain as DomainPage;
use Tests\Browser\Pages\Home;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DomainTest extends DuskTestCase
{

    /**
     * Test domain info page (unauthenticated)
     */
    public function testDomainInfoUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/domain/123')->on(new Home());
        });
    }

    /**
     * Test domain info page (non-existing domain id)
     */
    public function testDomainInfo404(): void
    {
        $this->browse(function (Browser $browser) {
            // FIXME: I couldn't make loginAs() method working

            // Note: Here we're also testing that unauthenticated request
            //       is passed to logon form and then "redirected" to the requested page
            $browser->visit('/domain/123')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123')
                // TODO: the check below could look simpler, but we can't
                //       just remove the callback argument. We'll create
                //       Browser wrappen in future, then we could create expectError() method
                ->with(new Error('404'), function (Browser $browser) {
                });
        });
    }

    /**
     * Test domain info page (existing domain)
     *
     * @depends testDomainInfo404
     */
    public function testDomainInfo(): void
    {
        $this->browse(function (Browser $browser) {
            // Unconfirmed domain
            $domain = Domain::where('namespace', 'kolab.org')->first();
            $domain->status ^= Domain::STATUS_CONFIRMED;
            $domain->save();

            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainPage())
                ->whenAvailable('@verify', function (Browser $browser) use ($domain) {
                    // Make sure the domain is confirmed now
                    // TODO: Test verification process failure
                    $domain->status |= Domain::STATUS_CONFIRMED;
                    $domain->save();

                    $browser->assertSeeIn('pre', $domain->namespace)
                        ->assertSeeIn('pre', $domain->hash())
                        ->click('button');
                })
                ->whenAvailable('@config', function (Browser $browser) use ($domain) {
                    $browser->assertSeeIn('pre', $domain->namespace);
                })
                ->assertMissing('@verify')
                ->with(new Toast(Toast::TYPE_SUCCESS), function (Browser $browser) {
                    $browser->assertToastTitle('')
                        ->assertToastMessage('Domain verified successfully')
                        ->closeToast();
                });

            // Check that confirmed domain page contains only the config box
            $browser->visit('/domain/' . $domain->id)
                ->on(new DomainPage())
                ->assertMissing('@verify')
                ->assertPresent('@config');
        });
    }
}
