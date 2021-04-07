<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomControlsTest extends TestCaseDusk
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
     * Test fullscreen buttons
     *
     * @group openvidu
     */
    public function testFullscreen(): void
    {
        // TODO: This test does not work in headless mode
        $this->markTestIncomplete();
/*
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
        });
*/
    }

    /**
     * Test nickname and muting audio/video
     *
     * @group openvidu
     */
    public function testNicknameAndMuting(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Join the room as an owner (authenticate)
            $owner->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'john')
                ->keys('@setup-nickname-input', '{enter}') // Test form submit with Enter key
                ->waitFor('@session');

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                //->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Assert current UI state
            $owner->assertToolbar([
                    'audio' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'video' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'screen' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'chat' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'fullscreen' => RoomPage::BUTTON_ENABLED,
                    'options' => RoomPage::BUTTON_ENABLED,
                    'logout' => RoomPage::BUTTON_ENABLED,
                ])
                ->whenAvailable('div.meet-video.self', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertAudioMuted('video', true)
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->whenAvailable('div.meet-video:not(.self)', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertVisible('.meet-nickname')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2);

            // Assert current UI state
            $guest->assertToolbar([
                    'audio' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                    'video' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'screen' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'chat' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'fullscreen' => RoomPage::BUTTON_ENABLED,
                    'logout' => RoomPage::BUTTON_ENABLED,
                ])
                ->whenAvailable('div.meet-video:not(.self)', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->whenAvailable('div.meet-video.self', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2);

            // Test nickname change propagation

            $guest->setNickname('div.meet-video.self', 'guest');
            $owner->waitFor('div.meet-video:not(.self) .meet-nickname')
                ->assertSeeIn('div.meet-video:not(.self) .meet-nickname', 'guest');

            // Test muting audio
            $owner->click('@menu button.link-audio')
                ->assertToolbarButtonState('audio', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertVisible('div.meet-video.self .status .status-audio');

            // FIXME: It looks that we can't just check the <video> element state
            //        We might consider using OpenVidu API to make sure
            $guest->waitFor('div.meet-video:not(.self) .status .status-audio');

            // Test unmuting audio
            $owner->click('@menu button.link-audio')
                ->assertToolbarButtonState('audio', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertMissing('div.meet-video.self .status .status-audio');

            $guest->waitUntilMissing('div.meet-video:not(.self) .status .status-audio');

            // Test muting audio with a keyboard shortcut (key 'm')
            $owner->driver->getKeyboard()->sendKeys('m');
            $owner->assertToolbarButtonState('audio', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertVisible('div.meet-video.self .status .status-audio');

            $guest->waitFor('div.meet-video:not(.self) .status .status-audio')
                ->assertAudioMuted('div.meet-video:not(.self) video', true);

            // Test unmuting audio with a keyboard shortcut (key 'm')
            $owner->driver->getKeyboard()->sendKeys('m');
            $owner->assertToolbarButtonState('audio', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertMissing('div.meet-video.self .status .status-audio');

            $guest->waitUntilMissing('div.meet-video:not(.self) .status .status-audio')
                ->assertAudioMuted('div.meet-video:not(.self) video', false);

            // Test muting video
            $owner->click('@menu button.link-video')
                ->assertToolbarButtonState('video', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertVisible('div.meet-video.self .status .status-video');

            // FIXME: It looks that we can't just check the <video> element state
            //        We might consider using OpenVidu API to make sure
            $guest->waitFor('div.meet-video:not(.self) .status .status-video');

            // Test unmuting video
            $owner->click('@menu button.link-video')
                ->assertToolbarButtonState('video', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertMissing('div.meet-video.self .status .status-video');

            $guest->waitUntilMissing('div.meet-video:not(.self) .status .status-video');

            // Test muting other user
            $guest->with('div.meet-video:not(.self)', function (Browser $browser) {
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
     */
    public function testChat(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Join the room as an owner
            $owner->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'john')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                // ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Test chat elements

            $owner->click('@menu button.link-chat')
                ->assertToolbarButtonState('chat', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertVisible('@chat')
                ->assertVisible('@session')
                ->assertFocused('@chat-input')
                ->assertElementsCount('@chat-list .message', 0)
                ->keys('@chat-input', 'test1', '{enter}')
                ->assertValue('@chat-input', '')
                ->waitFor('@chat-list .message')
                ->assertElementsCount('@chat-list .message', 1)
                ->assertSeeIn('@chat-list .message .nickname', 'john')
                ->assertSeeIn('@chat-list .message div:last-child', 'test1');

            $guest->waitFor('@menu button.link-chat .badge')
                ->assertTextRegExp('@menu button.link-chat .badge', '/^1$/')
                ->click('@menu button.link-chat')
                ->assertToolbarButtonState('chat', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED)
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

            $guest->setNickname('div.meet-video.self', 'guest')
                ->keys('@chat-input', 'guest2', '{enter}')
                ->assertElementsCount('@chat-list .message', 2)
                ->assertSeeIn('@chat-list .message:last-child .nickname', 'guest')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest2');

            $owner->assertElementsCount('@chat-list .message', 2)
                ->assertSeeIn('@chat-list .message:last-child .nickname', 'guest')
                ->assertSeeIn('@chat-list .message:last-child div:last-child', 'guest2');

            // TODO: Test text chat features, e.g. link handling
        });
    }

    /**
     * Test screen sharing
     *
     * @group openvidu
     */
    public function testShareScreen(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Join the room as an owner
            $owner->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'john')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Test screen sharing
            $owner->assertToolbarButtonState('screen', RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED)
                ->assertElementsCount('@session div.meet-video', 1)
                ->click('@menu button.link-screen')
                ->whenAvailable('div.meet-video:not(.self)', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@subscribers .meet-subscriber', 1)
                ->assertToolbarButtonState('screen', RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED);

            $guest
                ->whenAvailable('div.meet-video.screen', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@subscribers .meet-subscriber', 1);
        });
    }
}
