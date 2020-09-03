<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomControlsTest extends TestCaseDusk
{
    /**
     * Test fullscreen buttons
     *
     * @group openvidu
     */
    public function testFullscreen(): void
    {
        // TODO: This test does not work in --headless mode
        $this->markTestIncomplete();

        // Make sure there's no session yet
        $room = Room::where('name', 'john')->first();
        if ($room->session_id) {
            $room->session_id = null;
            $room->save();
        }

        $this->browse(function (Browser $browser) {
            // Join the room as an owner (authenticate)
            $browser->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@setup-form')
                ->assertVisible('@login-form')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->assertMissing('@login-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->click('@setup-button')
                ->waitFor('@session')

                // Test fullscreen for the whole room
                ->click('@menu button.link-fullscreen.closed')
                ->assertVisible('@toolbar')
                ->assertVisible('@session')
                ->assertMissing('nav')
                ->assertMissing('@menu button.link-fullscreen.closed')
                ->click('@menu button.link-fullscreen.open')
                ->assertVisible('nav')

                // Test fullscreen for the participant video
                ->click('@session button.link-fullscreen.closed')
                ->assertVisible('@session')
                ->assertMissing('@toolbar')
                ->assertMissing('nav')
                ->assertMissing('@session button.link-fullscreen.closed')
                ->click('@session button.link-fullscreen.open')
                ->assertVisible('nav')
                ->assertVisible('@toolbar');

                // TODO: Test video fullscreen while in the room fullscreen
        });
    }

    /**
     * Test nickname and muting audio/video
     *
     * @group openvidu
     */
    public function testNicknameAndMuting(): void
    {
        // Make sure there's no session yet
        $room = Room::where('name', 'john')->first();
        if ($room->session_id) {
            $room->session_id = null;
            $room->save();
        }

        $this->browse(function (Browser $owner, Browser $guest) {
            // Join the room as an owner (authenticate)
            $owner->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'john')
                ->click('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->click('@setup-button')
                ->waitFor('@session');

            // Assert current UI state
            $owner->assertToolbar([
                    'audio' => true,
                    'video' => true,
                    'screen' => false,
                    'chat' => false,
                    'fullscreen' => true,
                    'logout' => true,
                ])
                ->whenAvailable('div.meet-video.publisher', function (Browser $browser) {
                    $browser->assertVisible('video')
                        ->assertAudioMuted('video', true)
                        ->assertSeeIn('.nickname', 'john')
                        ->assertMissing('.nickname button')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->whenAvailable('div.meet-video:not(.publisher)', function (Browser $browser) {
                    $browser->assertMissing('video')
                        ->assertMissing('.nickname')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertVisible('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2);

            // Assert current UI state
            $guest->assertToolbar([
                    'audio' => false,
                    'video' => false,
                    'screen' => false,
                    'chat' => false,
                    'fullscreen' => true,
                    'logout' => true,
                ])
                ->whenAvailable('div.meet-video.publisher', function (Browser $browser) {
                    $browser->assertVisible('video')
                        //->assertAudioMuted('video', true)
                        ->assertVisible('.nickname button')
                        ->assertMissing('.nickname span')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertVisible('.status .status-video');
                })
                ->whenAvailable('div.meet-video:not(.publisher)', function (Browser $browser) {
                    $browser->assertVisible('video')
                        ->assertSeeIn('.nickname', 'john')
                        ->assertMissing('.nickname button')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2);

            // Test nickname change propagation

            // Use script() because type() does not work with this contenteditable widget
            $guest->setNickname('div.meet-video.publisher', 'guest');
            $owner->waitFor('div.meet-video:not(.publisher) .nickname')
                ->assertSeeIn('div.meet-video:not(.publisher) .nickname', 'guest');

            // Test muting audio
            $owner->click('@menu button.link-audio')
                ->assertToolbarButtonState('audio', false)
                ->assertVisible('div.meet-video.publisher .status .status-audio');

            // FIXME: It looks that we can't just check the <video> element state
            //        We might consider using OpenVidu API to make sure
            $guest->waitFor('div.meet-video:not(.publisher) .status .status-audio');

            // Test unmuting audio
            $owner->click('@menu button.link-audio')
                ->assertToolbarButtonState('audio', true)
                ->assertMissing('div.meet-video.publisher .status .status-audio');

            $guest->waitUntilMissing('div.meet-video:not(.publisher) .status .status-audio');

            // Test muting video
            $owner->click('@menu button.link-video')
                ->assertToolbarButtonState('video', false)
                ->assertVisible('div.meet-video.publisher .status .status-video');

            // FIXME: It looks that we can't just check the <video> element state
            //        We might consider using OpenVidu API to make sure
            $guest->waitFor('div.meet-video:not(.publisher) .status .status-video');

            // Test unmuting video
            $owner->click('@menu button.link-video')
                ->assertToolbarButtonState('video', true)
                ->assertMissing('div.meet-video.publisher .status .status-video');

            $guest->waitUntilMissing('div.meet-video:not(.publisher) .status .status-video');

            // Test muting other user
            $guest->with('div.meet-video:not(.publisher)', function (Browser $browser) {
                $browser->click('.controls button.link-audio')
                    ->assertAudioMuted('video', true)
                    ->assertVisible('.controls button.link-audio.text-danger')
                    ->click('.controls button.link-audio')
                    ->assertAudioMuted('video', false)
                    ->assertVisible('.controls button.link-audio:not(.text-danger)');
            });
        });
    }

    /**
     * Test text chat
     *
     * @group openvidu
     * @depends testNicknameAndMuting
     */
    public function testChat(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Join the room as an owner
            $owner->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'john')
                ->click('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->click('@setup-button')
                ->waitFor('@session');

            // Test chat elements

            $owner->click('@menu button.link-chat')
                ->assertToolbarButtonState('chat', true)
                ->assertVisible('@chat')
                ->assertVisible('@session')
                ->assertFocused('@chat-input')
                ->assertElementsCount('@chat-list .message', 0)
                ->keys('@chat-input', 'test1', '{enter}')
                ->assertValue('@chat-input', '')
                ->assertElementsCount('@chat-list .message', 1)
                ->assertSeeIn('@chat-list .message .nickname', 'john')
                ->assertSeeIn('@chat-list .message div:last-child', 'test1');

            $guest->waitFor('@menu button.link-chat .badge')
                ->assertSeeIn('@menu button.link-chat .badge', '1')
                ->click('@menu button.link-chat')
                ->assertToolbarButtonState('chat', true)
                ->assertMissing('@menu button.link-chat .badge')
                ->assertVisible('@chat')
                ->assertVisible('@session')
                ->assertElementsCount('@chat-list .message', 1)
                ->assertSeeIn('@chat-list .message .nickname', 'john')
                ->assertSeeIn('@chat-list .message div:last-child', 'test1');

            // Test the number of (hidden) incoming messages
            $guest->click('@menu button.link-chat')
                ->assertMissing('@chat');

            $owner->keys('@chat-input', 'test2', '{enter}', 'test3', '{enter}')
                ->assertElementsCount('@chat-list .message', 1)
                ->assertSeeIn('@chat-list .message .nickname', 'john')
                ->assertElementsCount('@chat-list .message div', 4)
                ->assertSeeIn('@chat-list .message div:last-child', 'test3');

            $guest->waitFor('@menu button.link-chat .badge')
                ->assertSeeIn('@menu button.link-chat .badge', '2')
                ->click('@menu button.link-chat')
                ->assertElementsCount('@chat-list .message', 1)
                ->assertSeeIn('@chat-list .message .nickname', 'john')
                ->assertSeeIn('@chat-list .message div:last-child', 'test3')
                ->keys('@chat-input', 'guest1', '{enter}')
                ->assertElementsCount('@chat-list .message', 2)
                ->assertMissing('@chat-list .message:last-child .nickname')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest1');

            $owner->assertElementsCount('@chat-list .message', 2)
                ->assertMissing('@chat-list .message:last-child .nickname')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest1');

            // Test nickname change is propagated to chat messages

            $guest->setNickname('div.meet-video.publisher', 'guest')
                ->keys('@chat-input', 'guest2', '{enter}')
                ->assertElementsCount('@chat-list .message', 2)
                ->assertSeeIn('@chat-list .message:last-child .nickname', 'guest')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest2');

            $owner->assertElementsCount('@chat-list .message', 2)
                ->assertSeeIn('@chat-list .message:last-child .nickname', 'guest')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest2');

            // TODO: Test chat features, e.g. link handling
        });
    }

    /**
     * Test screen sharing
     *
     * @group openvidu
     */
    public function testShareScreen(): void
    {
        // TODO: I'm not sure it's possible to test that at all
        $this->markTestIncomplete();
    }
}
