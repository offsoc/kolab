<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomInterpretersTest extends TestCaseDusk
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
     * Test language interpreted channels functionality
     *
     * @group openvidu
     */
    public function testInterpreters(): void
    {
        $this->browse(function (Browser $owner, Browser $interpreter, Browser $guest) {
            $page = new RoomPage('john');
            // Join the room as an owner (authenticate)
            $owner->visit($page)
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'John')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest-to-be-interpreter
            $interpreter->visit($page)
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'Interpreter')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Assert current UI state, make the guest an interpreter
            $owner->waitFor('@session .meet-video.self')
                ->waitFor('@session .meet-video:not(.self)')
                ->assertSeeIn('@session .meet-video:not(.self)', 'Interpreter')
                ->click('@session .meet-video:not(.self) .meet-nickname')
                ->whenAvailable('@session .meet-video:not(.self) .dropdown-menu', function ($browser) {
                    $browser->assertVisible('.interpreting')
                        ->assertSeeIn('.interpreting h6', 'Language interpreter')
                        ->select('.interpreting select', 'en');
                })
                ->assertMissing('@session .meet-video:not(.self) .dropdown-menu')
                ->waitFor('@session div.meet-subscriber')
                ->assertUserIcon('@session div.meet-subscriber', RoomPage::ICO_INTERPRETER)
                ->assertToolbarButtonState('channel', RoomPage::BUTTON_ENABLED)
                ->assertMissing('@menu button.link-channel .badge');

            // Assert current UI state
            $interpreter->waitFor('@session .meet-video.self svg.interpreter')
                ->assertUserIcon('@session .meet-video.self', RoomPage::ICO_INTERPRETER)
                ->assertMissing('@menu button.link-channel')
                ->assertAudioMuted('@session .meet-video.self video', true) // always muted video of self
                ->assertAudioMuted('@session .meet-video:not(.self) video', false); // unmuted other publisher (owner)

            // In another browser act as a guest-subscriber
            // Test using a channel by subscriber
            $guest->visit($page)
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'Guest')
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session .meet-subscriber.self') // self
                ->waitFor('@session .meet-subscriber:not(.self)') // interpreter
                ->waitFor('@session .meet-video') // owner
                ->waitFor('@menu button.link-channel')
                ->assertToolbarButtonState('channel', RoomPage::BUTTON_ENABLED)
                ->assertMissing('@menu button.link-channel .badge')
                ->assertUserIcon('@session .meet-subscriber.self', RoomPage::ICO_USER) // self
                ->assertUserIcon('@session .meet-subscriber:not(.self)', RoomPage::ICO_INTERPRETER) // interpreter
                ->assertMissing('@session .meet-subscriber:not(.self) video') // hidden interpreter video
                ->assertAudioMuted('@session .meet-video video', false) // unmuted owner
                ->assertAudioMuted('@session .meet-subscriber:not(.self) video', true) // muted interpreter
                // select a channel
                ->click('@menu button.link-channel')
                ->whenAvailable('#channel-select .dropdown-menu', function ($browser) {
                    $browser->assertSeeIn('a:first-child.active', '- none -')
                        ->assertSeeIn('a:nth-child(2):not(.active)', 'English')
                        ->assertMissing('a:nth-child(3)')
                        ->click('a:nth-child(2)');
                })
                ->waitFor('@menu button.link-channel .badge')
                ->assertSeeIn('@menu button.link-channel .badge', 'EN')
                ->assertAudioMuted('@session .meet-video video', true) // muted owner
                ->assertAudioMuted('@session .meet-subscriber:not(.self) video', false) // unmuted interpreter
                // Unselect a channel
                ->click('@menu button.link-channel')
                ->whenAvailable('#channel-select .dropdown-menu', function ($browser) {
                    $browser->assertVisible('a:first-child:not(.active)')
                        ->assertVisible('a:nth-child(2).active')
                        ->click('a:first-child');
                })
                ->waitUntilMissing('@menu button.link-channel .badge')
                ->assertAudioMuted('@session .meet-video video', false) // unmuted owner
                ->assertAudioMuted('@session .meet-subscriber:not(.self) video', true); // muted interpreter

            // Test setting a channel by publisher
            $owner->assertUserIcon('@session .meet-video.self', RoomPage::ICO_MODERATOR) // self
                ->assertUserIcon('@session .meet-subscriber:nth-child(1)', RoomPage::ICO_INTERPRETER) // interpreter
                ->assertUserIcon('@session .meet-subscriber:nth-child(2)', RoomPage::ICO_USER) // guest-subscriber
                ->assertMissing('@session .meet-subscriber:nth-child(1) video') // hidden interpreter video
                ->assertAudioMuted('@session .meet-video video', true) // always muted owner's self video
                ->assertAudioMuted('@session .meet-subscriber:nth-child(1) video', true) // muted interpreter
                // select a channel
                ->click('@menu button.link-channel')
                ->whenAvailable('#channel-select .dropdown-menu', function ($browser) {
                    $browser->assertSeeIn('a:first-child.active', '- none -')
                        ->assertSeeIn('a:nth-child(2):not(.active)', 'English')
                        ->assertMissing('a:nth-child(3)')
                        ->click('a:nth-child(2)');
                })
                ->waitFor('@menu button.link-channel .badge')
                ->assertSeeIn('@menu button.link-channel .badge', 'EN')
                ->assertAudioMuted('@session .meet-video video', true) // always muted self video
                ->assertAudioMuted('@session .meet-subscriber:nth-child(1) video', false) // unmuted interpreter
                // Unselect a channel
                ->click('@menu button.link-channel')
                ->whenAvailable('#channel-select .dropdown-menu', function ($browser) {
                    $browser->assertVisible('a:first-child:not(.active)')
                        ->assertVisible('a:nth-child(2).active')
                        ->click('a:first-child');
                })
                ->waitUntilMissing('@menu button.link-channel .badge')
                ->assertAudioMuted('@session .meet-video video', true) // always muted video of self
                ->assertAudioMuted('@session .meet-subscriber:nth-child(1) video', true); // muted interpreter

            // Remove interpreting role
            $owner->click('@session .meet-subscriber:nth-child(1) .meet-nickname')
                ->whenAvailable('@session .meet-subscriber:nth-child(1) .dropdown-menu', function ($browser) {
                    $browser->assertSelected('.interpreting select', 'en')
                        ->select('.interpreting select', '');
                })
                ->waitFor('div.meet-video:not(.self)')
                ->assertUserIcon('div.meet-video:not(.self)', RoomPage::ICO_USER)
                ->assertMissing('@menu button.link-channel');

            $guest->waitUntilMissing('@menu button.link-channel');

            // TODO: Test what happens for users with a selectd channel when the interpreter is removed
            // TODO: Test two interpreters
        });
    }
}
