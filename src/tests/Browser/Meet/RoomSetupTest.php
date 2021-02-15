<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Menu;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomSetupTest extends TestCaseDusk
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

            // FIXME: Maybe it would be better to just display the usual 404 Not Found error page?

            $browser->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->assertVisible('@setup-form')
                ->assertSeeIn('@setup-status-message', "The room does not exist.")
                ->assertButtonDisabled('@setup-button');
        });
    }

    /**
     * Test the room setup page
     *
     * @group openvidu
     */
    public function testRoomSetup(): void
    {
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
                ->assertVisible('@setup-form .input-group:nth-child(1) svg')
                ->assertAttribute('@setup-form .input-group:nth-child(1) .input-group-text', 'title', 'Microphone')
                ->assertVisible('@setup-mic-select')
                ->assertVisible('@setup-form .input-group:nth-child(2) svg')
                ->assertAttribute('@setup-form .input-group:nth-child(2) .input-group-text', 'title', 'Camera')
                ->assertVisible('@setup-cam-select')
                ->assertVisible('@setup-form .input-group:nth-child(3) svg')
                ->assertAttribute('@setup-form .input-group:nth-child(3) .input-group-text', 'title', 'Nickname')
                ->assertValue('@setup-nickname-input', '')
                ->assertAttribute('@setup-nickname-input', 'placeholder', 'Your name')
                ->assertMissing('@setup-password-input')
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
            // In one browser window act as a guest
            $guest->visit(new RoomPage('john'))
                ->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertSeeIn(
                    '@setup-status-message',
                    "The room is closed. Please, wait for the owner to start the session."
                )
                ->assertSeeIn('@setup-button', "I'm the owner");

            // In another window join the room as the owner (authenticate)
            $browser->on(new RoomPage('john'))
                ->assertSeeIn('@setup-button', "I'm the owner")
                ->clickWhenEnabled('@setup-button')
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
                ->waitFor('@setup-status-message')
                ->assertSeeIn('@setup-status-message', "The room is closed. It will be open for others after you join.")
                ->assertSeeIn('@setup-button', "JOIN")
                ->type('@setup-nickname-input', 'john')
                // Join the room (click the button twice, to make sure it does not
                // produce redundant participants/subscribers in the room)
                ->clickWhenEnabled('@setup-button')
                ->pause(10)
                ->click('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->whenAvailable('div.meet-video.self', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'dashboard', 'logout']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'tos', 'dashboard', 'logout']);
                });
            }

            // After the owner "opened the room" guest should be able to join
            $guest->waitUntilMissing('@setup-status-message', 10)
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                //->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->whenAvailable('div.meet-video.self', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertVisible('.meet-nickname')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertMissing('.controls button.link-audio')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->whenAvailable('div.meet-video:not(.self)', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertSeeIn('.meet-nickname', 'john')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertMissing('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2)
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'signup', 'login']);
                });

            if ($guest->isDesktop()) {
                $guest->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'tos', 'signup', 'login']);
                });
            }

            // Check guest's elements in the owner's window
            $browser
                ->whenAvailable('div.meet-video:not(.self)', function (Browser $browser) {
                    $browser->waitFor('video')
                        ->assertVisible('.meet-nickname')
                        ->assertVisible('.controls button.link-fullscreen')
                        ->assertVisible('.controls button.link-audio')
                        ->assertMissing('.controls button.link-setup')
                        ->assertVisible('.status .status-audio')
                        ->assertMissing('.status .status-video');
                })
                ->assertElementsCount('@session div.meet-video', 2);

            // Test leaving the room

            // Guest is leaving
            $guest->click('@menu button.link-logout')
                ->waitForLocation('/login');

            // Expect the participant removed from other users windows
            $browser->waitUntilMissing('@session div.meet-video:not(.self)');

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
                //->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Leave the room as the room owner
            // TODO: Test leaving the room by closing the browser window,
            //       it should not destroy the session
            $browser->click('@menu button.link-logout')
                ->waitForLocation('/dashboard');

            // Expect other participants be informed about the end of the session
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

    /**
     * Test two subscribers-only users in a room
     *
     * @group openvidu
     * @depends testTwoUsersInARoom
     */
    public function testSubscribers(): void
    {
        $this->browse(function (Browser $browser, Browser $guest) {
            // Join the room as the owner
            $browser->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->waitFor('@setup-status-message')
                ->type('@setup-nickname-input', 'john')
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->whenAvailable('@subscribers .meet-subscriber.self', function (Browser $browser) {
                    $browser->assertSeeIn('.meet-nickname', 'john');
                })
                ->assertElementsCount('@session div.meet-video', 0)
                ->assertElementsCount('@session video', 0)
                ->assertElementsCount('@session .meet-subscriber', 1)
                ->assertToolbar([
                    'audio' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'video' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'screen' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'hand' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'chat' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'fullscreen' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                    'options' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                    'logout' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                ]);

            // After the owner "opened the room" guest should be able to join
            // In one browser window act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitUntilMissing('@setup-status-message', 10)
                ->assertSeeIn('@setup-button', "JOIN")
                // Join the room, disable cam/mic
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->whenAvailable('@subscribers .meet-subscriber.self', function (Browser $browser) {
                    $browser->assertVisible('.meet-nickname');
                })
                ->whenAvailable('@subscribers .meet-subscriber:not(.self)', function (Browser $browser) {
                    $browser->assertSeeIn('.meet-nickname', 'john');
                })
                ->assertElementsCount('@session div.meet-video', 0)
                ->assertElementsCount('@session video', 0)
                ->assertElementsCount('@session div.meet-subscriber', 2)
                ->assertToolbar([
                    'audio' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'video' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'screen' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_DISABLED,
                    'hand' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'chat' => RoomPage::BUTTON_INACTIVE | RoomPage::BUTTON_ENABLED,
                    'fullscreen' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                    'logout' => RoomPage::BUTTON_ACTIVE | RoomPage::BUTTON_ENABLED,
                ]);

            // Check guest's elements in the owner's window
            $browser
                ->whenAvailable('@subscribers .meet-subscriber:not(.self)', function (Browser $browser) {
                    $browser->assertVisible('.meet-nickname');
                })
                ->assertElementsCount('@session div.meet-video', 0)
                ->assertElementsCount('@session video', 0)
                ->assertElementsCount('@session .meet-subscriber', 2);

            // Test leaving the room

            // Guest is leaving
            $guest->click('@menu button.link-logout')
                ->waitForLocation('/login');

            // Expect the participant removed from other users windows
            $browser->waitUntilMissing('@session .meet-subscriber:not(.self)');
        });
    }

    /**
     * Test demoting publisher to a subscriber
     *
     * @group openvidu
     * @depends testSubscribers
     */
    public function testDemoteToSubscriber(): void
    {
        $this->browse(function (Browser $browser, Browser $guest1, Browser $guest2) {
            // Join the room as the owner
            $browser->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->waitFor('@setup-status-message')
                ->type('@setup-nickname-input', 'john')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->waitFor('@session video');

            // In one browser window act as a guest
            $guest1->visit(new RoomPage('john'))
                ->waitUntilMissing('@setup-status-message', 10)
                ->assertSeeIn('@setup-button', "JOIN")
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->waitFor('div.meet-video.self')
                ->waitFor('div.meet-video:not(.self)')
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@session video', 2)
                ->assertElementsCount('@session div.meet-subscriber', 0)
                // assert there's no moderator-related features for this guess available
                ->click('@session .meet-video.self .meet-nickname')
                ->whenAvailable('@session .meet-video.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertMissing('.permissions');
                })
                ->click('@session .meet-video:not(.self) .meet-nickname')
                ->pause(50)
                ->assertMissing('.dropdown-menu');

            // Demote the guest to a subscriber
            $browser
                ->waitFor('div.meet-video.self')
                ->waitFor('div.meet-video:not(.self)')
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@session video', 2)
                ->assertElementsCount('@session .meet-subscriber', 0)
                ->click('@session .meet-video:not(.self) .meet-nickname')
                ->whenAvailable('@session .meet-video:not(.self) .dropdown-menu', function (Browser $browser) {
                    $browser->assertSeeIn('.action-role-publisher', 'Audio & Video publishing')
                        ->click('.action-role-publisher')
                        ->waitUntilMissing('.dropdown-menu');
                })
                ->waitUntilMissing('@session .meet-video:not(.self)')
                ->waitFor('@session div.meet-subscriber')
                ->assertElementsCount('@session div.meet-video', 1)
                ->assertElementsCount('@session video', 1)
                ->assertElementsCount('@session div.meet-subscriber', 1);

            $guest1
                ->waitUntilMissing('@session .meet-video.self')
                ->waitFor('@session div.meet-subscriber')
                ->assertElementsCount('@session div.meet-video', 1)
                ->assertElementsCount('@session video', 1)
                ->assertElementsCount('@session div.meet-subscriber', 1);

            // Join as another user to make sure the role change is propagated to new connections
            $guest2->visit(new RoomPage('john'))
                ->waitUntilMissing('@setup-status-message', 10)
                ->assertSeeIn('@setup-button', "JOIN")
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form')
                ->waitFor('div.meet-subscriber:not(.self)')
                ->assertElementsCount('@session div.meet-video', 1)
                ->assertElementsCount('@session video', 1)
                ->assertElementsCount('@session div.meet-subscriber', 2)
                ->click('@toolbar .link-logout');

            // Promote the guest back to a publisher
            $browser
                ->click('@session .meet-subscriber .meet-nickname')
                ->whenAvailable('@session .meet-subscriber .dropdown-menu', function (Browser $browser) {
                    $browser->assertSeeIn('.action-role-publisher', 'Audio & Video publishing')
                        ->assertNotChecked('.action-role-publisher input')
                        ->click('.action-role-publisher')
                        ->waitUntilMissing('.dropdown-menu');
                })
                ->waitFor('@session .meet-video:not(.self) video')
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@session video', 2)
                ->assertElementsCount('@session div.meet-subscriber', 0);

            $guest1
                ->with(new Dialog('#media-setup-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Media setup')
                        ->click('@button-action');
                })
                ->waitFor('@session .meet-video.self')
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@session video', 2)
                ->assertElementsCount('@session div.meet-subscriber', 0);

            // Demote the owner to a subscriber
            $browser
                ->click('@session .meet-video.self .meet-nickname')
                ->whenAvailable('@session .meet-video.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertSeeIn('.action-role-publisher', 'Audio & Video publishing')
                        ->assertChecked('.action-role-publisher input')
                        ->click('.action-role-publisher')
                        ->waitUntilMissing('.dropdown-menu');
                })
                ->waitUntilMissing('@session .meet-video.self')
                ->waitFor('@session div.meet-subscriber.self')
                ->assertElementsCount('@session div.meet-video', 1)
                ->assertElementsCount('@session video', 1)
                ->assertElementsCount('@session div.meet-subscriber', 1);

            // Promote the owner to a publisher
            $browser
                ->click('@session .meet-subscriber.self .meet-nickname')
                ->whenAvailable('@session .meet-subscriber.self .dropdown-menu', function (Browser $browser) {
                    $browser->assertSeeIn('.action-role-publisher', 'Audio & Video publishing')
                        ->assertNotChecked('.action-role-publisher input')
                        ->click('.action-role-publisher')
                        ->waitUntilMissing('.dropdown-menu');
                })
                ->waitUntilMissing('@session .meet-subscriber.self')
                ->with(new Dialog('#media-setup-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Media setup')
                        ->click('@button-action');
                })
                ->waitFor('@session div.meet-video.self')
                ->assertElementsCount('@session div.meet-video', 2)
                ->assertElementsCount('@session video', 2)
                ->assertElementsCount('@session div.meet-subscriber', 0);
        });
    }

    /**
     * Test the media setup dialog
     *
     * @group openvidu
     * @depends testDemoteToSubscriber
     */
    public function testMediaSetupDialog(): void
    {
        $this->browse(function (Browser $browser, $guest) {
            // Join the room as the owner
            $browser->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->waitFor('@setup-status-message')
                ->type('@setup-nickname-input', 'john')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form');

            // In one browser window act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitUntilMissing('@setup-status-message', 10)
                ->assertSeeIn('@setup-button', "JOIN")
                ->select('@setup-mic-select', '')
                ->select('@setup-cam-select', '')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form');

            $browser->waitFor('@session video')
                ->click('.controls button.link-setup')
                ->with(new Dialog('#media-setup-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Media setup')
                        ->assertVisible('form video')
                        ->assertVisible('form > div:nth-child(1) video')
                        ->assertVisible('form > div:nth-child(1) .volume')
                        ->assertVisible('form > div:nth-child(2) svg')
                        ->assertAttribute('form > div:nth-child(2) .input-group-text', 'title', 'Microphone')
                        ->assertVisible('form > div:nth-child(2) select')
                        ->assertVisible('form > div:nth-child(3) svg')
                        ->assertAttribute('form > div:nth-child(3) .input-group-text', 'title', 'Camera')
                        ->assertVisible('form > div:nth-child(3) select')
                        ->assertMissing('@button-cancel')
                        ->assertSeeIn('@button-action', 'Close')
                        ->click('@button-action');
                })
                ->assertMissing('#media-setup-dialog')
                // Test mute audio and video
                ->click('.controls button.link-setup')
                ->with(new Dialog('#media-setup-dialog'), function (Browser $browser) {
                    $browser->select('form > div:nth-child(2) select', '')
                        ->select('form > div:nth-child(3) select', '')
                        ->click('@button-action');
                })
                ->assertMissing('#media-setup-dialog')
                ->assertVisible('@session .meet-video .status .status-audio')
                ->assertVisible('@session .meet-video .status .status-video');

            $guest->waitFor('@session video')
                ->assertVisible('@session .meet-video .status .status-audio')
                ->assertVisible('@session .meet-video .status .status-video');
        });
    }
}
