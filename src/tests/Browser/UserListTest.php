<?php

namespace Tests\Browser;

use App\User;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\Browser\Pages\UserList;
use Tests\TestCaseDusk;

class UserListTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test users list page (unauthenticated)
     */
    public function testListUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/users')->on(new Home());
        });
    }

    /**
     * Test users list page
     */
    public function testList(): void
    {
        $this->browse(function (Browser $browser) {
            // Test that the page requires authentication
            // Test the list
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-users', 'User accounts')
                ->click('@links .link-users')
                ->on(new UserList())
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertElementsCount('tbody tr', 4)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'jack@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'joe@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(3) a', 'john@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(4) a', 'ned@kolab.org')
                        ->assertMissing('tfoot');
                });

            // Test searching
            $browser->assertValue('@search input', '')
                ->assertAttribute('@search input', 'placeholder', 'User email address or name')
                ->assertSeeIn('@search button', 'Search')
                ->type('@search input', 'jo')
                ->click('@search button')
                ->waitUntilMissing('@app .app-loader')
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')
                        ->assertElementsCount('tbody tr', 2)
                        ->assertSeeIn('tbody tr:nth-child(1) a', 'joe@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) a', 'john@kolab.org')
                        ->assertMissing('tfoot');
                })
                // test empty result
                ->type('@search input', 'jojo')
                ->click('@search button')
                ->waitUntilMissing('@app .app-loader')
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tfoot tr')
                        ->assertSeeIn('tfoot tr', "There are no users in this account.");
                })
                // reset search
                ->vueClear('@search input')
                ->keys('@search input', '{enter}')
                ->waitUntilMissing('@app .app-loader')
                ->whenAvailable('@table', function (Browser $browser) {
                    $browser->waitFor('tbody tr')->assertElementsCount('tbody tr', 4);
                });

            // TODO: Test paging

            $browser->click('@table tr:nth-child(3)')
                ->on(new UserInfo())
                ->assertSeeIn('#user-info .card-title', 'User account')
                ->with('@general', function (Browser $browser) {
                    $browser->assertValue('#email', 'john@kolab.org');
                });
        });
    }
}
