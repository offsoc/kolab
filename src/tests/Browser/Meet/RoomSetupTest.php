<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomSetupTest extends TestCaseDusk
{

    /**
     * Test non-existing room
     *
     * @group openvidu
     */
    public function testRoomNonExistingRoom(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new RoomPage('unknown'))
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'tos', 'login']);
                });
            } else {
                $browser->assertMissing('#footer-menu .navbar-nav');
            }

            $browser->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->assertVisible('@setup-form')
                ->assertSeeIn('@setup-status-message', "The room does not exist.")
                ->assertMissing('@setup-button');
        });
    }

    /**
     * Test the room setup page
     *
     * @group openvidu
     */
    public function testRoomSetup(): void
    {
        // Make sure there's no session yet
        $room = Room::where('name', 'john')->first();
        if ($room->session_id) {
            $room->session_id = null;
            $room->save();
        }

        $this->browse(function (Browser $browser) {
            $browser->visit(new RoomPage('john'))
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'tos', 'login']);
                });
            } else {
                $browser->assertMissing('#footer-menu .navbar-nav');
            }

            // Note: I've found out that if I have another Chrome instance running
            //       that uses media, here the media devices will not be available

            // TODO: Test enabling/disabling cam/mic in the setup widget

            $browser->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->assertVisible('@setup-form')
                ->assertSeeIn('@setup-title', 'Set up your session')
                ->assertVisible('@setup-video')
                ->assertSeeIn('@setup-form .form-group:nth-child(1) label', 'Microphone')
                ->assertVisible('@setup-mic-select')
                ->assertSeeIn('@setup-form .form-group:nth-child(2) label', 'Camera')
                ->assertVisible('@setup-cam-select')
                ->assertSeeIn('@setup-form .form-group:nth-child(3) label', 'Nickname')
                ->assertValue('@setup-nickname-input', '')
                ->assertSeeIn(
                    '@setup-status-message',
                    "The room is closed. Please, wait for the owner to start the session."
                )
                ->assertSeeIn('@setup-button', "I'm the owner");
        });
    }

    /**
     * Test two users in a room (joining/leaving and some basic functionality)
     *
     * @group openvidu
     * @depends testRoomSetup
     */
    public function testTwoUsersInARoom(): void
    {
        $this->browse(function (Browser $browser, Browser $guest) {
            // Join the room as an owner (authenticate)
            $browser->on(new RoomPage('john'))
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
                ->assertSeeIn('@setup-status-message', "The room is closed. It will be open for others after you join.")
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'john')
                // Join the room
                ->click('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->assertToolbar([
                    'audio' => true,
                    'video' => true,
                    'screen' => false,
                    'chat' => false,
                    'fullscreen' => true,
                    'logout' => true,
                ])
                ->whenAvailable('div.meet-video.publisher', function (Browser $browser) {
                    $browser->assertVisible('video')
                        ->assertSeeIn('.nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['support', 'contact', 'webmail', 'logout']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['support', 'contact', 'webmail', 'logout']);
                });
            }

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->click('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->assertToolbar([
                    'audio' => false,
                    'video' => false,
                    'screen' => false,
                    'chat' => false,
                    'fullscreen' => true,
                    'logout' => true,
                ])
                ->whenAvailable('div.meet-video.publisher', function (Browser $browser) {
                    $browser->assertVisible('video')
                        ->assertVisible('.nickname')
                        ->assertMissing('.nickname span')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertVisible('.status .status-video');
                })
                ->whenAvailable('div.meet-video:not(.publisher)', function (Browser $browser) {
                    $browser->assertVisible('video')
                        ->assertSeeIn('.nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2)
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                });

            if ($guest->isDesktop()) {
                $guest->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'tos', 'login']);
                });
            }

            // Check guest's elements in the owner's window
            $browser->waitFor('@session div.meet-video:nth-child(2)')
                ->assertElementsCount('@session div.meet-video', 2)
                ->whenAvailable('div.meet-video:not(.publisher)', function (Browser $browser) {
                    $browser->assertMissing('video')
                        ->assertMissing('.nickname')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertVisible('.status .status-video');
                });

            // Test leaving the room

            // Guest is leaving
            $guest->click('@menu button.link-logout')
                ->waitForLocation('/login');

            // Expect the participant removed from other users windows
            $browser->waitUntilMissing('@session div.meet-video:nth-child(2)');

            // Join the room as guest again
            $guest->visit(new RoomPage('john'))
                ->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-status-message')
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->click('@setup-button')
                ->waitFor('@session');

            // Leave the room as the room owner
            $browser->click('@menu button.link-logout')
                ->waitForLocation('/dashboard');

            // Expect other participants be informed about the end of session
            $guest->with(new Dialog('#leave-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Room closed')
                        ->assertSeeIn('@body', "The session has been closed by the room owner.")
                        ->assertMissing('@button-cancel')
                        ->assertSeeIn('@button-action', 'Close')
                        ->click('@button-action');
            })
                ->assertMissing('#leave-dialog')
                ->waitForLocation('/login');
        });
    }
}
