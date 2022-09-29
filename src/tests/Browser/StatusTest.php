<?php

namespace Tests\Browser;

use App\Domain;
use App\User;
use Carbon\Carbon;
use Tests\Browser;
use Tests\Browser\Components\Status;
use Tests\Browser\Components\Toast;
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

        $domain_status = Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED;
        DB::statement("UPDATE domains SET status = (status | {$domain_status})"
            . " WHERE namespace = 'kolab.org'");
        DB::statement("UPDATE users SET status = (status | " . User::STATUS_IMAP_READY . ")"
            . " WHERE email = 'john@kolab.org'");
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $domain_status = Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED;
        DB::statement("UPDATE domains SET status = (status | {$domain_status})"
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
        // Unconfirmed domain and user
        $domain = Domain::where('namespace', 'kolab.org')->first();

        if ($domain->isConfirmed()) {
            $domain->status ^= Domain::STATUS_CONFIRMED;
            $domain->save();
        }

        $john = $this->getTestUser('john@kolab.org');

        $john->created_at = Carbon::now();

        if ($john->isImapReady()) {
            $john->status ^= User::STATUS_IMAP_READY;
        }

        $john->save();

        $this->browse(function ($browser) use ($john, $domain) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->with(new Status(), function ($browser) use ($john) {
                    $browser->assertSeeIn('@body', 'We are preparing your account')
                        ->assertProgress(71, 'Creating a mailbox...', 'pending')
                        ->assertMissing('#status-verify')
                        ->assertMissing('#status-link')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text');

                    $john->status |= User::STATUS_IMAP_READY;
                    $john->save();

                    // Wait for auto-refresh, expect domain-confirmed step
                    $browser->pause(6000)
                        ->assertSeeIn('@body', 'Your account is almost ready')
                        ->assertProgress(85, 'Verifying an ownership of a custom domain...', 'failed')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-verify')
                        ->assertVisible('#status-link');
                })
                // check if the link to domain info page works
                ->click('#status-link')
                ->on(new DomainInfo())
                ->back()
                ->on(new Dashboard())
                ->with(new Status(), function ($browser) {
                    $browser->assertMissing('@refresh-button')
                        ->assertProgress(85, 'Verifying an ownership of a custom domain...', 'failed');
                });

            // Confirm the domain and wait until the whole status box disappears
            $domain->status |= Domain::STATUS_CONFIRMED;
            $domain->save();

            // This should take less than 10 seconds
            $browser->waitUntilMissing('@status', 10);
        });

        // Test the Refresh button
        if ($domain->isConfirmed()) {
            $domain->status ^= Domain::STATUS_CONFIRMED;
            $domain->save();
        }

        $john->created_at = Carbon::now()->subSeconds(3600);

        if ($john->isImapReady()) {
            $john->status ^= User::STATUS_IMAP_READY;
        }

        $john->save();

        $this->browse(function ($browser) use ($john, $domain) {
            $browser->visit(new Dashboard())
                ->with(new Status(), function ($browser) use ($john, $domain) {
                    $browser->assertSeeIn('@body', 'We are preparing your account')
                        ->assertProgress(71, 'Creating a mailbox...', 'failed')
                        ->assertVisible('@refresh-button')
                        ->assertVisible('@refresh-text');

                    $browser->click('@refresh-button')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Setup process has been pushed. Please wait.');

                    $john->status |= User::STATUS_IMAP_READY;
                    $john->save();
                    $domain->status |= Domain::STATUS_CONFIRMED;
                    $domain->save();
                })
                ->waitUntilMissing('@status', 10);
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
        $domain->created_at = Carbon::now();
        $domain->status = Domain::STATUS_NEW | Domain::STATUS_ACTIVE | Domain::STATUS_LDAP_READY;
        $domain->save();

        // side-step
        $this->assertFalse($domain->isNew());
        $this->assertTrue($domain->isActive());
        $this->assertTrue($domain->isLdapReady());
        $this->assertTrue($domain->isExternal());

        $this->assertFalse($domain->isHosted());
        $this->assertFalse($domain->isConfirmed());
        $this->assertFalse($domain->isVerified());
        $this->assertFalse($domain->isSuspended());
        $this->assertFalse($domain->isDeleted());

        $this->browse(function ($browser) use ($domain) {
            // Test auto-refresh
            $browser->on(new Dashboard())
                ->click('@links a.link-domains')
                ->on(new DomainList())
                ->waitFor('@table tbody tr')
                // Assert domain status icon
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-globe.text-danger')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Not Ready')
                ->click('@table tbody tr:first-child td:first-child a')
                ->on(new DomainInfo())
                ->with(new Status(), function ($browser) {
                    $browser->assertSeeIn('@body', 'We are preparing the domain')
                        ->assertProgress(50, 'Verifying a custom domain...', 'pending')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-link')
                        ->assertMissing('#status-verify');
                });

            $domain->status |= Domain::STATUS_VERIFIED;
            $domain->save();

            // This should take less than 10 seconds
            $browser->waitFor('@status.process-failed')
                ->with(new Status(), function ($browser) {
                    $browser->assertSeeIn('@body', 'The domain is almost ready')
                        ->assertProgress(75, 'Verifying an ownership of a custom domain...', 'failed')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-link')
                        ->assertVisible('#status-verify');
                });

            $domain->status |= Domain::STATUS_CONFIRMED;
            $domain->save();

            // Test Verify button
            $browser->click('@status #status-verify')
                ->assertToast(Toast::TYPE_SUCCESS, 'Domain verified successfully.')
                ->waitUntilMissing('@status')
                ->waitUntilMissing('@verify')
                ->assertVisible('@config');
        });
    }

    /**
     * Test user status on users list and user info page
     *
     * @depends testDashboard
     */
    public function testUserStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->created_at = Carbon::now();
        if ($john->isImapReady()) {
            $john->status ^= User::STATUS_IMAP_READY;
        }
        $john->save();

        $domain = Domain::where('namespace', 'kolab.org')->first();
        if ($domain->isConfirmed()) {
            $domain->status ^= Domain::STATUS_CONFIRMED;
            $domain->save();
        }

        $this->browse(function ($browser) use ($john, $domain) {
            $browser->visit(new Dashboard())
                ->click('@links a.link-users')
                ->on(new UserList())
                ->waitFor('@table tbody tr')
                // Assert user status icons
                ->assertVisible('@table tbody tr:first-child td:first-child svg.fa-user.text-success')
                ->assertText('@table tbody tr:first-child td:first-child svg title', 'Active')
                ->assertVisible('@table tbody tr:nth-child(3) td:first-child svg.fa-user.text-danger')
                ->assertText('@table tbody tr:nth-child(3) td:first-child svg title', 'Not Ready')
                ->click('@table tbody tr:nth-child(3) td:first-child a')
                ->on(new UserInfo())
                ->with('@form', function (Browser $browser) {
                    // Assert state in the user edit form
                    $browser->assertSeeIn('div.row:nth-child(1) label', 'Status')
                        ->assertSeeIn('div.row:nth-child(1) #status', 'Not Ready');
                })
                ->with(new Status(), function ($browser) use ($john) {
                    $browser->assertSeeIn('@body', 'We are preparing the user account')
                        ->assertProgress(71, 'Creating a mailbox...', 'pending')
                        ->assertMissing('#status-verify')
                        ->assertMissing('#status-link')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text');


                    $john->status |= User::STATUS_IMAP_READY;
                    $john->save();

                    // Wait for auto-refresh, expect domain-confirmed step
                    $browser->pause(6000)
                        ->assertSeeIn('@body', 'The user account is almost ready')
                        ->assertProgress(85, 'Verifying an ownership of a custom domain...', 'failed')
                        ->assertMissing('@refresh-button')
                        ->assertMissing('@refresh-text')
                        ->assertMissing('#status-verify')
                        ->assertVisible('#status-link');
                })
                ->assertSeeIn('#status', 'Active');

            // Confirm the domain and wait until the whole status box disappears
            $domain->status |= Domain::STATUS_CONFIRMED;
            $domain->save();

            // This should take less than 10 seconds
            $browser->waitUntilMissing('@status', 10);
        });
    }
}
