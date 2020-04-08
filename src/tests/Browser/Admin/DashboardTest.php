<?php

namespace Tests\Browser\Admin;

use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DashboardTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->setSetting('external_email', null);

        parent::tearDown();
    }

    /**
     * Test user search
     */
    public function testSearch(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', 'jeroen', true)
                ->on(new Dashboard())
                ->assertFocused('@search input')
                ->assertMissing('@search table');

            // Test search with no results
            $browser->type('@search input', 'unknown')
                ->click('@search form button')
                ->assertToast(Toast::TYPE_INFO, '', '0 user accounts have been found.')
                ->assertMissing('@search table');

            $john = $this->getTestUser('john@kolab.org');
            $jack = $this->getTestUser('jack@kolab.org');
            $jack->setSetting('external_email', 'john.doe.external@gmail.com');

            // Test search with multiple results
            $browser->type('@search input', 'john.doe.external@gmail.com')
                ->click('@search form button')
                ->assertToast(Toast::TYPE_INFO, '', '2 user accounts have been found.')
                ->whenAvailable('@search table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 2);
                    // TODO: Assert table content
                });

            // Test search with single record result -> redirect to user page
            $browser->type('@search input', 'kolab.org')
                ->click('@search form button')
                ->assertMissing('@search table')
                ->waitForLocation('/user/' . $john->id)
                ->waitFor('#user-info')
                ->assertVisible('#user-info .card-title', $john->email);
        });
    }
}
