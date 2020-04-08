<?php

namespace Tests\Browser;

use App\Domain;
use App\User;
use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\DomainInfo;
use Tests\Browser\Pages\DomainList;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\Browser\Pages\UserList;
use Tests\TestCaseDusk;
use Illuminate\Support\Facades\DB;

class StatusTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        DB::statement("UPDATE domains SET status = (status | " . Domain::STATUS_CONFIRMED . ")"
            . " WHERE namespace = 'kolab.org'");
        DB::statement("UPDATE users SET status = (status | " . User::STATUS_IMAP_READY . ")"
            . " WHERE email = 'john@kolab.org'");
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        DB::statement("UPDATE domains SET status = (status | " . Domain::STATUS_CONFIRMED . ")"
            . " WHERE namespace = 'kolab.org'");
        DB::statement("UPDATE users SET status = (status | " . User::STATUS_IMAP_READY . ")"
            . " WHERE email = 'john@kolab.org'");

        parent::tearDown();
    }

    /**
     * Test account status in the Dashboard
     */
    public function testDashboard(): void
    {
        // Unconfirmed domain
        $domain = Domain::where('namespace', 'kolab.org')->first();
        $domain->status ^= Domain::STATUS_CONFIRMED;
        $domain->save();

        $this->browse(function ($browser) use ($domain) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->whenAvailable('@status', function ($browser) {
                    $browser->assertSeeIn('.card-title', 'Account status:')
                        ->assertSeeIn('.card-title span.text-danger', 'Not ready')
                        ->with('ul.status-list', function ($browser) {
                            $browser->assertElementsCount('li', 7)
                                ->assertVisible('li:nth-child(1) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(1) span', 'User registered')
                                ->assertVisible('li:nth-child(2) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(2) span', 'User created')
                                ->assertVisible('li:nth-child(3) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(3) span', 'User mailbox created')
                                ->assertVisible('li:nth-child(4) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(4) span', 'Custom domain registered')
                                ->assertVisible('li:nth-child(5) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(5) span', 'Custom domain created')
                                ->assertVisible('li:nth-child(6) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(6) span', 'Custom domain verified')
                                ->assertVisible('li:nth-child(7) svg.fa-square')
                                ->assertSeeIn('li:nth-child(7) a', 'Custom domain ownership verified');
                        });
                });

            // Confirm the domain and wait until the whole status box disappears
            $domain->status |= Domain::STATUS_CONFIRMED;
            $domain->save();

            // At the moment, this may take about 10 seconds
            $browser->waitUntilMissing('@status', 15);
        });
    }

    /**
     * Test domain status on domains list and domain info page
     *
     * @depends testDashboard
     */
    public function testDomainStatus(): void
    {
        $domain = Domain::where('namespace', 'kolab.org')->first();
        $domain->status ^= Domain::STATUS_CONFIRMED;
        $domain->save();

        $this->browse(function ($browser) use ($domain) {
            $browser->on(new Dashboard())
                ->click('@links a.link-domains')
                ->on(new DomainList())
                // Assert domain status icon
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-globe.text-danger')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Not Ready')
                ->click('@table tbody tr:first-child td:first-child a')
                ->on(new DomainInfo())
                ->whenAvailable('@status', function ($browser) {
                    $browser->assertSeeIn('.card-title', 'Domain status:')
                        ->assertSeeIn('.card-title span.text-danger', 'Not ready')
                        ->with('ul.status-list', function ($browser) {
                            $browser->assertElementsCount('li', 4)
                                ->assertVisible('li:nth-child(1) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(1) span', 'Custom domain registered')
                                ->assertVisible('li:nth-child(2) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(2) span', 'Custom domain created')
                                ->assertVisible('li:nth-child(3) svg.fa-check-square')
                                ->assertSeeIn('li:nth-child(3) span', 'Custom domain verified')
                                ->assertVisible('li:nth-child(4) svg.fa-square')
                                ->assertSeeIn('li:nth-child(4) span', 'Custom domain ownership verified');
                        });
                });

            // Confirm the domain and wait until the whole status box disappears
            $domain->status |= Domain::STATUS_CONFIRMED;
            $domain->save();

            // At the moment, this may take about 10 seconds
            $browser->waitUntilMissing('@status', 15);
        });
    }

    /**
     * Test user status on users list
     *
     * @depends testDashboard
     */
    public function testUserStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->status ^= User::STATUS_IMAP_READY;
        $john->save();

        $this->browse(function ($browser) {
            $browser->visit(new Dashboard())
                ->click('@links a.link-users')
                ->on(new UserList())
                // Assert user status icons
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-user.text-success')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Active')
                ->assertVisible('@table tbody tr:nth-child(3) td:first-child svg.fa-user.text-danger')
                ->assertText('@table tbody tr:nth-child(3) td:first-child svg title', 'Not Ready')
                ->click('@table tbody tr:nth-child(3) td:first-child a')
                ->on(new UserInfo())
                ->with('@form', function (Browser $browser) {
                    // Assert stet in the user edit form
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Status')
                        ->assertSeeIn('div.row:nth-child(1) #status', 'Not Ready');
                });

            // TODO: The status should also be live-updated here
            //       Maybe when we have proper websocket communication
        });
    }
}
