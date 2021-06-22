<?php

namespace Tests\Browser\Reseller;

use App\Domain;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\Domain as DomainPage;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DomainTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test domain info page (unauthenticated)
     */
    public function testDomainUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $domain = $this->getTestDomain('kolab.org');
            $browser->visit('/domain/' . $domain->id)->on(new Home());
        });
    }

    /**
     * Test domain info page
     */
    public function testDomainInfo(): void
    {
        $this->browse(function (Browser $browser) {
            $domain = $this->getTestDomain('kolab.org');
            $domain_page = new DomainPage($domain->id);
            $reseller = $this->getTestUser('reseller@kolabnow.com');
            $user = $this->getTestUser('john@kolab.org');
            $user_page = new UserPage($user->id);

            // Goto the domain page
            $browser->visit(new Home())
                ->submitLogon('reseller@kolabnow.com', 'reseller', true)
                ->on(new Dashboard())
                ->visit($user_page)
                ->on($user_page)
                ->click('@nav #tab-domains')
                ->pause(1000)
                ->click('@user-domains table tbody tr:first-child td a');

            $browser->on($domain_page)
                ->assertSeeIn('@domain-info .card-title', 'kolab.org')
                ->with('@domain-info form', function (Browser $browser) use ($domain) {
                    $browser->assertElementsCount('.row', 2)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(1) #domainid', "{$domain->id} ({$domain->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status span.text-success', 'Active');
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 2);

            // Assert Configuration tab
            $browser->assertSeeIn('@nav #tab-config', 'Configuration')
                ->with('@domain-config', function (Browser $browser) {
                    $browser->assertSeeIn('pre#dns-verify', 'kolab-verify.kolab.org.')
                        ->assertSeeIn('pre#dns-config', 'kolab.org.');
                });
        });
    }

    /**
     * Test suspending/unsuspending a domain
     *
     * @depends testDomainInfo
     */
    public function testSuspendAndUnsuspend(): void
    {
        $this->browse(function (Browser $browser) {
            $domain = $this->getTestDomain('domainscontroller.com', [
                    'status' => Domain::STATUS_NEW | Domain::STATUS_ACTIVE
                        | Domain::STATUS_LDAP_READY | Domain::STATUS_CONFIRMED
                        | Domain::STATUS_VERIFIED,
                    'type' => Domain::TYPE_EXTERNAL,
            ]);

            $browser->visit(new DomainPage($domain->id))
                ->assertVisible('@domain-info #button-suspend')
                ->assertMissing('@domain-info #button-unsuspend')
                ->click('@domain-info #button-suspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'Domain suspended successfully.')
                ->assertSeeIn('@domain-info #status span.text-warning', 'Suspended')
                ->assertMissing('@domain-info #button-suspend')
                ->click('@domain-info #button-unsuspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'Domain unsuspended successfully.')
                ->assertSeeIn('@domain-info #status span.text-success', 'Active')
                ->assertVisible('@domain-info #button-suspend')
                ->assertMissing('@domain-info #button-unsuspend');
        });
    }
}
