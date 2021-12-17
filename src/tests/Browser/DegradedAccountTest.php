<?php

namespace Tests\Browser;

use App\User;
use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\DistlistList;
use Tests\Browser\Pages\DomainList;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserList;
use Tests\Browser\Pages\ResourceList;
use Tests\Browser\Pages\SharedFolderList;
use Tests\TestCaseDusk;

class DegradedAccountTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $john = $this->getTestUser('john@kolab.org');

        if (!$john->isDegraded()) {
            $john->status |= User::STATUS_DEGRADED;
            User::where('id', $john->id)->update(['status' => $john->status]);
        }

        $this->clearBetaEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        if ($john->isDegraded()) {
            $john->status ^= User::STATUS_DEGRADED;
            User::where('id', $john->id)->update(['status' => $john->status]);
        }

        $this->clearBetaEntitlements();

        parent::tearDown();
    }

    /**
     * Test acting as an owner of a degraded account
     */
    public function testDegradedAccountOwner(): void
    {
        // Add beta+distlist entitlements
        $john = $this->getTestUser('john@kolab.org');
        $this->addBetaEntitlement($john, ['beta-distlists', 'beta-resources', 'beta-shared-folders']);

        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment');

            // Goto /users and assert that the warning is also displayed there
            $browser->visit(new UserList())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment')
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-warning') // Jack
                        ->assertText('tbody tr:nth-child(2) td:first-child svg.text-warning title', 'Degraded')
                        ->assertVisible('tbody tr:nth-child(3) td:first-child svg.text-warning') // John
                        ->assertText('tbody tr:nth-child(3) td:first-child svg.text-warning title', 'Degraded');
                })
                ->assertMissing('button.create-user');

            // Goto /domains and assert that the warning is also displayed there
            $browser->visit(new DomainList())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment')
                ->assertMissing('button.create-domain');

            // Goto /distlists and assert that the warning is also displayed there
            $browser->visit(new DistlistList())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment')
                ->assertMissing('button.create-list');

            // Goto /resources and assert that the warning is also displayed there
            $browser->visit(new ResourceList())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment')
                ->assertMissing('button.create-resource');

            // Goto /shared-folders and assert that the warning is also displayed there
            $browser->visit(new SharedFolderList())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertSeeIn('#status-degraded p.alert', 'Please, make a payment')
                ->assertMissing('button.create-resource');

            // Test that /rooms is not accessible
            $browser->visit('/rooms')
                ->waitFor('#app > #error-page')
                ->assertSeeIn('#error-page .code', '403');
        });
    }

    /**
     * Test acting as non-owner of a degraded account
     */
    public function testDegradedAccountUser(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('#status-degraded p.alert', 'The account is degraded')
                ->assertDontSeeIn('#status-degraded p.alert', 'Please, make a payment');
        });
    }
}
