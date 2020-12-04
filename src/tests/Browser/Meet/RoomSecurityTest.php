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

        $this->clearBetaEntitlements();
        $this->assignBetaEntitlement('john@kolab.org', 'meet');

        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null]);
    }

    public function tearDown(): void
    {
        $this->clearBetaEntitlements();
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
                ->click('@setup-button')
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
                ->assertSeeIn('@setup-status-message.text-danger', "Please, provide a valid password.")
                ->assertVisible('@setup-form .input-group:nth-child(4) svg')
                ->assertAttribute('@setup-form .input-group:nth-child(4) .input-group-text', 'title', 'Password')
                ->assertAttribute('@setup-password-input', 'placeholder', 'Password')
                ->assertValue('@setup-password-input', '')
                ->assertSeeIn('@setup-button', "JOIN")
                // Try to join w/o password
                ->click('@setup-button')
                ->waitFor('#setup-password.is-invalid')
                // Try to join with a valid password
                ->type('#setup-password', 'pass')
                ->click('@setup-button')
                ->waitFor('@session');

            // Test removing the password
            $owner->click('@menu button.link-security')
                ->with(new Dialog('#security-options-dialog'), function (Browser $browser) use ($room) {
                    $browser->assertSeeIn('@title', 'Security options')
                        ->assertSeeIn('#password-input-text:not(.text-muted)', 'pass')
                        ->assertSeeIn('#password-clear-btn.btn-outline-danger', 'Clear password')
                        ->assertElementsCount('#password-input button', 1)
                        ->click('#password-clear-btn')
                        ->assertToast(Toast::TYPE_SUCCESS, 'Room configuration updated successfully.')
                        ->assertMissing('#password-input input')
                        ->assertSeeIn('#password-input-text.text-muted', 'none')
                        ->assertSeeIn('#password-set-btn', 'Set password')
                        ->assertElementsCount('#password-input button', 1)
                        ->click('@button-action');

                    $this->assertSame(null, $room->fresh()->getSetting('password'));
                });
        });
    }
}
