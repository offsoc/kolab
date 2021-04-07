<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomQATest extends TestCaseDusk
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
     * Test Q&A queue
     *
     * @group openvidu
     */
    public function testQA(): void
    {
        $this->browse(function (Browser $owner, Browser $guest1, Browser $guest2) {
            // Join the room as an owner (authenticate)
            $owner->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->type('@setup-nickname-input', 'John')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest (1)
            $guest1->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->type('@setup-nickname-input', 'Guest1')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Assert current UI state
            $owner->assertToolbarButtonState('hand', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->waitFor('div.meet-subscriber.self')
                ->assertMissing('@queue')
                ->click('@menu button.link-hand')
                ->waitFor('@queue .dropdown.self.moderated')
                ->assertSeeIn('@queue .dropdown.self.moderated', 'John')
                ->assertToolbarButtonState('hand', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED);

            // Assert current UI state
            $guest1->waitFor('@queue .dropdown')
                ->assertSeeIn('@queue .dropdown', 'John')
                ->assertElementsCount('@queue .dropdown', 1)
                ->waitFor('div.meet-subscriber.self')
                ->click('@menu button.link-hand')
                ->waitFor('@queue .dropdown.self')
                ->assertSeeIn('@queue .dropdown.self', 'Guest1')
                ->assertElementsCount('@queue .dropdown', 2)
                ->click('@menu button.link-hand')
                ->waitUntilMissing('@queue .dropdown.self')
                ->assertElementsCount('@queue .dropdown', 1)
                ->assertToolbarButtonState('hand', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED);

            // In another browser act as a guest (2)
            $guest2->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'Guest2')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@queue .dropdown')
                ->assertSeeIn('@queue .dropdown', 'John')
                ->assertElementsCount('@queue .dropdown', 1)
                ->assertMissing('@menu button.link-hand');

            // Demote the guest (2) to subscriber, assert Hand button in toolbar
            $owner->click('@session div.meet-video .meet-nickname')
                ->whenAvailable('@session div.meet-video .dropdown-menu', function ($browser) {
                    $browser->click('.action-role-publisher input');
                });

            // Guest (2) rises his hand
            $guest2->waitUntilMissing('@session .meet-video')
                ->waitFor('@menu button.link-hand')
                ->assertToolbarButtonState('hand', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->click('@menu button.link-hand')
                ->waitFor('@queue .dropdown.self')
                ->assertElementsCount('@queue .dropdown', 2);

            // Promote guest (2) to publisher
            $owner->waitFor('@queue .dropdown:not(.self)')
                ->pause(8000) // wait until it's not moving, otherwise click() will be possible
                ->click('@queue .dropdown:not(.self)')
                ->whenAvailable('@queue .dropdown:not(.self) .dropdown-menu', function ($browser) {
                    $browser->click('.action-role-publisher input');
                })
                ->waitUntilMissing('@queue .dropdown:not(.self)')
                ->waitFor('@session .meet-video');

            $guest1->waitFor('@session .meet-video')
                ->assertElementsCount('@queue .dropdown', 1);

            $guest2->waitFor('@session .meet-video')
                ->waitUntilMissing('@queue .dropdown.self')
                ->assertElementsCount('@queue .dropdown', 1);

            // Finally, do the same with the owner (last in the queue)
            $owner->click('@queue .dropdown.self')
                ->whenAvailable('@queue .dropdown.self .dropdown-menu', function ($browser) {
                    $browser->click('.action-role-publisher input');
                })
                ->waitUntilMissing('@queue')
                ->waitFor('@session .meet-video.self');

            $guest1->waitUntilMissing('@queue');
            $guest2->waitUntilMissing('@queue');
        });
    }
}
