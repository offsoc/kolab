<?php

namespace Tests\Browser\Meet;

use App\OpenVidu\Room;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomSecurityTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clearMeetEntitlements();
        $this->assignMeetEntitlement('john@kolab.org');

        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null]);
    }

    public function tearDown(): void
    {
        $this->clearMeetEntitlements();
        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null]);

        parent::tearDown();
    }

    /**
     * Test password protected room
     *
     * @group openvidu
     */
    public function testRoomPassword(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Make sure there's no session yet
            $room = Room::where('name', 'john')->first();
            if ($room->session_id) {
                $room->session_id = null;
                $room->save();
            }

            // Join the room as an owner (authenticate)
            $owner->visit(new RoomPage('john'))
                ->click('@setup-button')
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertMissing('@setup-password-input')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                // Enter Security option dialog
                ->click('@menu button.link-security')
                ->with(new Dialog('#security-options-dialog'), function (Browser $browser) use ($room) {
                    $browser->assertSeeIn('@title', 'Security options')
                        ->assertSeeIn('@button-action', 'Close')
                        ->assertElementsCount('.modal-footer button', 1)
                        ->assertSeeIn('#password-input .label', 'Password:')
                        ->assertSeeIn('#password-input-text.text-muted', 'none')
                        ->assertVisible('#password-input + small')
                        ->assertSeeIn('#password-set-btn', 'Set password')
                        ->assertElementsCount('#password-input button', 1)
                        ->assertMissing('#password-input input')
                        // Test setting a password
                        ->click('#password-set-btn')
                        ->assertMissing('#password-input-text')
                        ->assertVisible('#password-input input')
                        ->assertValue('#password-input input', '')
                        ->assertSeeIn('#password-input #password-save-btn', 'Save')
                        ->assertElementsCount('#password-input button', 1)
                        ->type('#password-input input', 'pass')
                        ->click('#password-input #password-save-btn')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Room configuration updated successfully.')
                        ->assertMissing('#password-input input')
                        ->assertSeeIn('#password-input-text:not(.text-muted)', 'pass')
                        ->assertSeeIn('#password-clear-btn.btn-outline-danger', 'Clear password')
                        ->assertElementsCount('#password-input button', 1)
                        ->click('@button-action');

                    $this->assertSame('pass', $room->fresh()->getSetting('password'));
                });

            // In another browser act as a guest, expect password required
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertSeeIn('@setup-status-message', "Please, provide a valid password.")
                ->assertVisible('@setup-form .input-group:nth-child(4) svg')
                ->assertAttribute('@setup-form .input-group:nth-child(4) .input-group-text', 'title', 'Password')
                ->assertAttribute('@setup-password-input', 'placeholder', 'Password')
                ->assertValue('@setup-password-input', '')
                ->assertSeeIn('@setup-button', "JOIN")
                // Try to join w/o password
                ->clickWhenEnabled('@setup-button')
                ->waitFor('#setup-password.is-invalid')
                // Try to join with a valid password
                ->type('#setup-password', 'pass')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session');

            // Test removing the password
            $owner->click('@menu button.link-security')
                ->with(new Dialog('#security-options-dialog'), function (Browser $browser) use ($room) {
                    $browser->assertSeeIn('@title', 'Security options')
                        ->assertSeeIn('#password-input-text:not(.text-muted)', 'pass')
                        ->assertSeeIn('#password-clear-btn.btn-outline-danger', 'Clear password')
                        ->assertElementsCount('#password-input button', 1)
                        ->click('#password-clear-btn')
                        ->assertToast(Toast::TYPE_SUCCESS, "Room configuration updated successfully.")
                        ->assertMissing('#password-input input')
                        ->assertSeeIn('#password-input-text.text-muted', 'none')
                        ->assertSeeIn('#password-set-btn', 'Set password')
                        ->assertElementsCount('#password-input button', 1)
                        ->click('@button-action');

                    $this->assertSame(null, $room->fresh()->getSetting('password'));
                });
        });
    }

    /**
     * Test locked room
     *
     * @group openvidu
     */
    public function testLockedRoom(): void
    {
        $this->browse(function (Browser $owner, Browser $guest) {
            // Make sure there's no session yet
            $room = Room::where('name', 'john')->first();
            if ($room->session_id) {
                $room->session_id = null;
                $room->save();
            }

            // Join the room as an owner (authenticate)
            $owner->visit(new RoomPage('john'))
                // ->click('@setup-button')
                // ->submitLogon('john@kolab.org', 'simple123')
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'John')
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@session')
                // Enter Security option dialog
                ->click('@menu button.link-security')
                ->with(new Dialog('#security-options-dialog'), function (Browser $browser) use ($room) {
                    $browser->assertSeeIn('@title', 'Security options')
                        ->assertSeeIn('#room-lock label', 'Locked room:')
                        ->assertVisible('#room-lock input[type=checkbox]:not(:checked)')
                        ->assertVisible('#room-lock + small')
                        // Test setting the lock
                        ->click('#room-lock input')
                        ->assertToast(Toast::TYPE_SUCCESS, "Room configuration updated successfully.")
                        ->click('@button-action');

                    $this->assertSame('true', $room->fresh()->getSetting('locked'));
                });

            // In another browser act as a guest
            $guest->visit(new RoomPage('john'))
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->assertButtonEnabled('@setup-button')
                ->assertSeeIn('@setup-button.btn-success', 'JOIN NOW')
                // try without the nickname
                ->clickWhenEnabled('@setup-button')
                ->waitFor('@setup-nickname-input.is-invalid')
                ->assertSeeIn(
                    '@setup-status-message',
                    "The room is locked. Please, enter your name and try again."
                )
                ->assertMissing('@setup-password-input')
                ->assertButtonEnabled('@setup-button')
                ->assertSeeIn('@setup-button.btn-success', 'JOIN NOW')
                ->type('@setup-nickname-input', 'Guest<p>')
                ->clickWhenEnabled('@setup-button')
                ->assertMissing('@setup-nickname-input.is-invalid')
                ->waitForText("Waiting for permission to join the room.")
                ->assertButtonDisabled('@setup-button');

            // Test denying the request (this will also test custom toasts)
            $owner
                ->whenAvailable(new Toast(Toast::TYPE_CUSTOM), function ($browser) {
                    $browser->assertToastTitle('Join request')
                        ->assertVisible('.toast-header svg.fa-user')
                        ->assertSeeIn('@message', 'Guest<p> requested to join.')
                        ->assertAttributeRegExp('@message img', 'src', '|^data:image|')
                        ->assertSeeIn('@message button.accept.btn-success', 'Accept')
                        ->assertSeeIn('@message button.deny.btn-danger', 'Deny')
                        ->click('@message button.deny');
                })
                ->waitUntilMissing('.toast')
                // wait 10 seconds to make sure the request message does not show up again
                ->pause(10 * 1000)
                ->assertMissing('.toast');

            // Test accepting the request
            $guest->refresh()
                ->waitFor('@setup-form')
                ->waitUntilMissing('@setup-status-message.loading')
                ->type('@setup-nickname-input', 'guest')
                ->clickWhenEnabled('@setup-button')
                ->waitForText("Waiting for permission to join the room.")
                ->assertButtonDisabled('@setup-button');

            $owner
                ->whenAvailable(new Toast(Toast::TYPE_CUSTOM), function ($browser) {
                    $browser->assertToastTitle('Join request')
                        ->assertSeeIn('@message', 'guest requested to join.')
                        ->click('@message button.accept');
                });

            // Guest automatically anters the room
            $guest->waitFor('@session', 12)
                // make sure he has no access to the Options menu
                ->waitFor('@session .meet-video:not(.self)')
                ->assertSeeIn('@session .meet-video:not(.self) .meet-nickname', 'John')
                // TODO: Assert title and icon
                ->click('@session .meet-video:not(.self) .meet-nickname')
                ->pause(100)
                ->assertMissing('.dropdown-menu');

            // Test dismissing the participant
            $owner->click('@session .meet-video:not(.self) .meet-nickname')
                ->waitFor('@session .meet-video:not(.self) .dropdown-menu')
                ->assertSeeIn('@session .meet-video:not(.self) .dropdown-menu > .action-dismiss', 'Dismiss')
                ->click('@session .meet-video:not(.self) .dropdown-menu > .action-dismiss')
                ->waitUntilMissing('.dropdown-menu')
                ->waitUntilMissing('@session .meet-video:not(.self)');

            // Expect a "end of session" dialog on the participant side
            $guest->with(new Dialog('#leave-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Room closed')
                        ->assertSeeIn('@body', "The session has been closed by the room owner.")
                        ->assertMissing('@button-cancel')
                        ->assertSeeIn('@button-action', 'Close');
            });
        });
    }
}
