<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Menu;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomModeratorTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->setupTestRoom();
    }

    public function tearDown(): void
    {
        $this->resetTestRoom();
        parent::tearDown();
    }

    /**
     * Test three users in a room, one will be promoted/demoted to/from a moderator
     *
     * @group openvidu
     */
    public function testModeratorPromotion(): void
    {
        $this->browse(function (Browser $browser, Browser $guest1, Browser $guest2) {
            // In one browser window join as a room owner
            $browser->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'John')
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In one browser window join as a guest (to be promoted)
            $guest1->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'Guest1')
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In one browser window join as a guest
            $guest2->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable mic
                ->select('@setup-mic-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Assert that only the owner is a moderator right now
            $guest1->waitFor('@session video')
                ->assertMissing('@session div.meet-video .meet-nickname') // guest2
                ->assertVisible('@session div.meet-subscriber.self svg.user') // self
                ->assertMissing('@session div.meet-subscriber.self svg.moderator') // self
                ->assertMissing('@session div.meet-subscriber:not(.self) svg.user') // owner
                ->assertVisible('@session div.meet-subscriber:not(.self) svg.moderator') // owner
                ->click('@session div.meet-subscriber.self .meet-nickname')
                ->whenAvailable('@session div.meet-subscriber.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertMissing('.permissions');
                })
                ->click('@session div.meet-subscriber:not(.self) .meet-nickname')
                ->assertMissing('.dropdown-menu');

            $guest2->waitFor('@session video')
                ->assertVisible('@session div.meet-video svg.user') // self
                ->assertMissing('@session div.meet-video svg.moderator') // self
                // the following 4 assertions used to be flaky on openvidu
                // because the order is different all the time
                ->assertMissing('@session div.meet-subscriber:nth-child(1) svg.user') // owner
                ->assertVisible('@session div.meet-subscriber:nth-child(1) svg.moderator') // owner
                ->assertVisible('@session div.meet-subscriber:nth-child(2) svg.user') // guest1
                ->assertMissing('@session div.meet-subscriber:nth-child(2) svg.moderator'); // guest1

            // Promote guest1 to a moderator
            $browser->waitFor('@session video')
                ->assertMissing('@session div.meet-subscriber.self svg.user') // self
                ->assertVisible('@session div.meet-subscriber.self svg.moderator') // self
                ->click('@session div.meet-subscriber.self .meet-nickname')
                ->whenAvailable('@session div.meet-subscriber.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertChecked('.action-role-moderator input')
                        ->assertDisabled('.action-role-moderator input');
                })
                ->click('@session div.meet-subscriber:not(.self) .meet-nickname')
                ->whenAvailable('@session div.meet-subscriber:not(.self) .dropdown-menu', function (Browser $browser) {
                    $browser->assertNotChecked('.action-role-moderator input')
                        ->click('.action-role-moderator input');
                });

            // Assert that we have two moderators now
            $guest2->waitFor('@session div.meet-subscriber:nth-child(2) svg.moderator')
                ->assertMissing('@session div.meet-subscriber:nth-child(2) svg.user'); // guest1

            $guest1->waitFor('@session div.meet-subscriber.self svg.moderator')
                ->assertMissing('@session div.meet-subscriber.self svg.user') // self
                ->assertVisible('@session div.meet-video svg.user') // guest2
                ->assertMissing('@session div.meet-video svg.moderator') // guest2
                ->assertMissing('@session div.meet-subscriber:not(.self) svg.user') // owner
                ->assertVisible('@session div.meet-subscriber:not(.self) svg.moderator') // owner
                ->click('@session div.meet-subscriber:not(.self) .meet-nickname') // owner
                ->assertMissing('@session div.meet-subscriber:not(.self) .dropdown-menu')
                ->click('@session div.meet-subscriber.self .meet-nickname')
                ->whenAvailable('@session div.meet-subscriber.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertChecked('.action-role-moderator input')
                        ->assertEnabled('.action-role-moderator input')
                        ->assertNotChecked('.action-role-publisher input')
                        ->assertEnabled('.action-role-publisher input');
                });

            $browser->waitFor('@session div.meet-subscriber:not(.self) svg.moderator')
                ->assertMissing('@session div.meet-subscriber:not(.self) svg.user');

            // Check if a moderator can unpublish another user
            $guest1->click('@session div.meet-video .meet-nickname')
                ->whenAvailable('@session div.meet-video .dropdown-menu', function (Browser $browser) {
                    $browser->assertNotChecked('.action-role-moderator input')
                        ->assertEnabled('.action-role-moderator input')
                        ->assertChecked('.action-role-publisher input')
                        ->assertEnabled('.action-role-publisher input')
                        ->click('.action-role-publisher input');
                })
                ->waitUntilMissing('@session div.meet-video');

            $guest2->waitUntilMissing('@session div.meet-video');

            // Demote guest1 back to a normal user
            $browser->waitFor('@session div.meet-subscriber:nth-child(3)')
                ->click('@session') // somehow needed to make the next line invoke the menu
                ->click('@session div.meet-subscriber:nth-child(2) .meet-nickname')
                ->whenAvailable('@session div.meet-subscriber:nth-child(2) .dropdown-menu', function ($browser) {
                    $browser->assertChecked('.action-role-moderator input')
                        ->click('.action-role-moderator input');
                })
                ->waitFor('@session div.meet-subscriber:nth-child(2) svg.user')
                ->assertMissing('@session div.meet-subscriber:nth-child(2) svg.moderator');

            $guest1->waitFor('@session div.meet-subscriber.self svg.user')
                ->assertMissing('@session div.meet-subscriber.self svg.moderator')
                ->click('@session div.meet-subscriber.self .meet-nickname')
                ->whenAvailable('@session .dropdown-menu', function (Browser $browser) {
                    $browser->assertMissing('.permissions');
                });
        });
    }
}
