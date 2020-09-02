<?php

namespace Tests\Browser\Meet;

use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\TestCaseDusk;

class RoomsTest extends TestCaseDusk
{

    /**
     * Test rooms page (unauthenticated)
     *
     * @group openvidu
     */
    public function testRoomsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/rooms')->on(new Home());
        });
    }

    /**
     * Test rooms page
     *
     * @group openvidu
     */
    public function testRooms(): void
    {
        $this->browse(function (Browser $browser) {
            $href = \config('app.url') . '/meet/john';

            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                // On dashboard click the "Video chat" link
                ->on(new Dashboard())
                ->assertSeeIn('@links a.link-chat', 'Video chat')
                ->click('@links a.link-chat')
                ->waitFor('#meet-rooms')
                ->waitFor('.card-text a')
                ->assertSeeIn('.card-title', 'Video chat')
                ->assertSeeIn('.card-text a', $href)
                ->assertAttribute('.card-text a', 'href', $href)
                ->click('.card-text a')
                ->on(new RoomPage('john'))
                // check that entering the room skips the logon form
                ->assertMissing('@toolbar')
                ->assertMissing('@menu')
                ->assertMissing('@session')
                ->assertMissing('@chat')
                ->assertMissing('@login-form')
                ->assertVisible('@setup-form')
                ->assertSeeIn('@setup-status-message', "The room is closed. It will be open for others after you join.")
                ->assertSeeIn('@setup-button', "JOIN")
                ->click('@setup-button')
                ->waitFor('@session')
                ->assertMissing('@setup-form');
        });
    }
}
