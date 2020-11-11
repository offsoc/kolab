<?php

namespace Tests\Browser\Meet;

use App\Sku;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\Browser\Pages\UserInfo;
use Tests\TestCaseDusk;

class RoomsTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->clearBetaEntitlements();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->clearBetaEntitlements();
        parent::tearDown();
    }

    /**
     * Test rooms page (unauthenticated and unauthorized)
     *
     * @group openvidu
     */
    public function testRoomsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/rooms')
                ->on(new Home())
                // User has no 'meet' entitlement yet, expect redirect to error page
                ->submitLogon('john@kolab.org', 'simple123', false)
                ->waitFor('#app > #error-page')
                ->assertSeeIn('#error-page .code', '403')
                ->assertSeeIn('#error-page .message', 'Access denied');
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
            $john = $this->getTestUser('john@kolab.org');
            $john->assignSku(Sku::where('title', 'beta')->first());

            // User has no 'meet' entitlement yet
            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertMissing('@links a.link-chat');

            // Goto user subscriptions, and enable 'meet' subscription
            $browser->visit('/user/' . $john->id)
                ->on(new UserInfo())
                ->with('@skus', function ($browser) {
                    $browser->click('#sku-input-meet');
                })
                ->click('button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, 'User data updated successfully.')
                ->click('.navbar-brand')
                ->on(new Dashboard())
                ->assertSeeIn('@links a.link-chat', 'Video chat')
                // Make sure the element also exists on Dashboard page load
                ->refresh()
                ->on(new Dashboard())
                ->assertSeeIn('@links a.link-chat', 'Video chat');

            // Test Video chat page
            $browser->click('@links a.link-chat')
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
