<?php

namespace Tests\Browser\Meet;

use App\Meet\Room;
use Tests\Browser;
use Tests\Browser\Components\SubscriptionSelect;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Meet\Room as RoomPage;
use Tests\Browser\Pages\Meet\RoomInfo;
use Tests\Browser\Pages\Meet\RoomList;
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

        Room::withTrashed()->whereNotIn('name', ['shared', 'john'])->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Room::withTrashed()->whereNotIn('name', ['shared', 'john'])->forceDelete();
        $room = $this->resetTestRoom('shared', ['acl' => ['jack@kolab.org, full']]);

        parent::tearDown();
    }

    /**
     * Test rooms page (unauthenticated)
     */
    public function testRoomsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/rooms')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', false)
                ->on(new RoomList());
        });
    }

    /**
     * Test rooms list page
     *
     * @group meet
     */
    public function testRooms(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');

            $browser->visit('/login')
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links a.link-chat', 'Video chat')
                // Test Video chat page
                ->click('@links a.link-chat')
                ->on(new RoomList())
                ->whenAvailable('@table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 2)
                        ->assertElementsCount('thead th', 3)
                        ->with('tbody tr:nth-child(1)', function ($browser) {
                            $browser->assertSeeIn('td:nth-child(1) a', 'john')
                                ->assertSeeIn('td:nth-child(2) a', "Standard room")
                                ->assertElementsCount('td.buttons button', 2)
                                ->assertAttribute('td.buttons button:nth-child(1)', 'title', 'Copy room location')
                                ->assertAttribute('td.buttons button:nth-child(2)', 'title', 'Enter the room');
                        })
                        ->with('tbody tr:nth-child(2)', function ($browser) {
                            $browser->assertSeeIn('td:nth-child(1) a', 'shared')
                                ->assertSeeIn('td:nth-child(2) a', "Shared room")
                                ->assertElementsCount('td.buttons button', 2)
                                ->assertAttribute('td.buttons button:nth-child(1)', 'title', 'Copy room location')
                                ->assertAttribute('td.buttons button:nth-child(2)', 'title', 'Enter the room');
                        })
                        ->click('tbody tr:nth-child(1) button:nth-child(2)');
                });

            $newWindow = collect($browser->driver->getWindowHandles())->last();
            $browser->driver->switchTo()->window($newWindow);

            $browser->on(new RoomPage('john'))
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

    /**
     * Test rooms create and edit and delete
     */
    public function testRoomCreateAndEditAndDelete(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $this->browse(function (Browser $browser) {
            // Test room creation
            $browser->visit(new RoomList())
                ->assertSeeIn('button.room-new', 'Create room')
                ->click('button.room-new')
                ->on(new RoomInfo())
                ->assertVisible('@intro p')
                ->assertElementsCount('@nav li', 1)
                ->assertSeeIn('@nav li a', 'General')
                ->with('@general form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Description')
                        ->assertFocused('.row:nth-child(1) input')
                        ->assertSeeIn('.row:nth-child(2) label', 'Subscriptions')
                        ->with(new SubscriptionSelect('@skus'), function ($browser) {
                            $browser->assertElementsCount('tbody tr', 2)
                                ->assertSubscription(
                                    0,
                                    "Standard conference room",
                                    "Audio & video conference room",
                                    "0,00 CHF/month"
                                )
                                ->assertSubscriptionState(0, true)
                                ->assertSubscription(
                                    1,
                                    "Group conference room",
                                    "Shareable audio & video conference room",
                                    "0,00 CHF/month"
                                )
                                ->assertSubscriptionState(1, false)
                                ->clickSubscription(1)
                                ->assertSubscriptionState(0, false)
                                ->assertSubscriptionState(1, true);
                        })
                        ->type('.row:nth-child(1) input', 'test123');
                })
                ->click('@general button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, "Room created successfully.")
                ->on(new RoomList())
                ->whenAvailable('@table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 3);
                });

            $room = Room::where('description', 'test123')->first();

            $this->assertTrue($room->hasSKU('group-room'));

            // Test room editing
            $browser->click("a[href=\"/room/{$room->id}\"]")
                ->on(new RoomInfo())
                ->assertSeeIn('.card-title', "Room: {$room->name}")
                ->assertVisible('@intro p')
                ->assertVisible("@intro a[href=\"/meet/{$room->name}\"]")
                ->assertElementsCount('@nav li', 2)
                ->assertSeeIn('@nav li:first-child a', 'General')
                ->with('@general form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Description')
                        ->assertFocused('.row:nth-child(1) input')
                        ->type('.row:nth-child(1) input', 'test321')
                        ->assertSeeIn('.row:nth-child(2) label', 'Subscriptions')
                        ->with(new SubscriptionSelect('@skus'), function ($browser) {
                            $browser->assertElementsCount('tbody tr', 2)
                                ->assertSubscription(
                                    0,
                                    "Standard conference room",
                                    "Audio & video conference room",
                                    "0,00 CHF/month"
                                )
                                ->assertSubscriptionState(0, false)
                                ->assertSubscription(
                                    1,
                                    "Group conference room",
                                    "Shareable audio & video conference room",
                                    "0,00 CHF/month"
                                )
                                ->assertSubscriptionState(1, true)
                                ->clickSubscription(0)
                                ->assertSubscriptionState(0, true)
                                ->assertSubscriptionState(1, false);
                        });
                })
                ->click('@general button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, "Room updated successfully.")
                ->on(new RoomList());

            $room->refresh();

            $this->assertSame('test321', $room->description);
            $this->assertFalse($room->hasSKU('group-room'));

            // Test room deleting
            $browser->visit('/room/' . $room->id)
                ->on(new Roominfo())
                ->assertSeeIn('button.button-delete', 'Delete room')
                ->click('button.button-delete')
                ->assertToast(Toast::TYPE_SUCCESS, "Room deleted successfully.")
                ->on(new RoomList())
                ->whenAvailable('@table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 2);
                });
        });
    }

    /**
     * Test room settings
     */
    public function testRoomSettings(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');
            $room = $this->getTestRoom('test', $john->wallets()->first());

            // Test that there's no Moderators for non-group rooms
            $browser->visit('/room/' . $room->id)
                ->on(new RoomInfo())
                ->assertSeeIn('@nav li:last-child a', 'Settings')
                ->click('@nav li:last-child a')
                ->with('@settings form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Password')
                        ->assertValue('.row:nth-child(1) input', '')
                        ->assertVisible('.row:nth-child(1) .form-text')
                        ->assertSeeIn('.row:nth-child(2) label', 'Locked room')
                        ->assertNotChecked('.row:nth-child(2) input')
                        ->assertVisible('.row:nth-child(2) .form-text')
                        ->assertSeeIn('.row:nth-child(3) label', 'Subscribers only')
                        ->assertNotChecked('.row:nth-child(3) input')
                        ->assertVisible('.row:nth-child(3) .form-text')
                        ->assertMissing('.row:nth-child(4)'); // no Moderators section on a standard room
                });

            $room->forceDelete();
            $room = $this->getTestRoom('test', $john->wallets()->first(), [], [], 'group-room');

            // Now we can assert and change all settings
            $browser->visit('/room/' . $room->id)
                ->on(new RoomInfo())
                ->assertSeeIn('@nav li:last-child a', 'Settings')
                ->click('@nav li:last-child a')
                ->with('@settings form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(4) label', 'Moderators')
                        ->assertVisible('.row:nth-child(4) .form-text')
                        ->type('#acl .input-group:first-child input', 'jack')
                        ->click('#acl a.btn');
                })
                ->click('@settings button[type=submit]')
                ->assertToast(Toast::TYPE_ERROR, "Form validation error")
                ->assertSeeIn('#acl + .invalid-feedback', "The specified email address is invalid.")
                ->with('@settings form', function ($browser) {
                    $browser->type('.row:nth-child(1) input', 'pass')
                        ->click('.row:nth-child(2) input')
                        ->click('.row:nth-child(3) input')
                        ->click('#acl .input-group:last-child a.btn')
                        ->type('#acl .input-group:first-child input', 'jack@kolab.org')
                        ->click('#acl a.btn');
                })
                ->click('@settings button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, "Room configuration updated successfully.");

            $config = $room->getConfig();

            $this->assertSame('pass', $config['password']);
            $this->assertSame(true, $config['locked']);
            $this->assertSame(true, $config['nomedia']);
            $this->assertSame(['jack@kolab.org, full'], $config['acl']);
        });
    }

    /**
     * Test acting as a non-controller user
     *
     * @group meet
     */
    public function testNonControllerRooms(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $room = $this->resetTestRoom('shared', [
                'password' => 'pass',
                'locked' => true,
                'nomedia' => true,
                'acl' => ['jack@kolab.org, full']
            ]);

        $this->browse(function (Browser $browser) use ($room, $jack) {
            $browser->visit(new Home())
                ->submitLogon('jack@kolab.org', 'simple123', true)
                ->on(new Dashboard())
                ->click('@links a.link-chat')
                ->on(new RoomList())
                ->assertMissing('button.room-new')
                ->whenAvailable('@table', function ($browser) {
                    $browser->assertElementsCount('tbody tr', 2); // one shared room, one owned room
                });

            // the owned room
            $owned = $jack->rooms()->first();
            $browser->visit('/room/' . $owned->id)
                ->on(new RoomInfo())
                ->assertSeeIn('.card-title', "Room: {$owned->name}")
                ->assertVisible('@intro p')
                ->assertVisible("@intro a[href=\"/meet/{$owned->name}\"]")
                ->assertMissing('button.button-delete')
                ->assertElementsCount('@nav li', 2)
                ->with('@general form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Description')
                        ->assertFocused('.row:nth-child(1) input')
                        ->assertSeeIn('.row:nth-child(2) label', 'Subscriptions')
                        ->with(new SubscriptionSelect('@skus'), function ($browser) {
                            $browser->assertElementsCount('tbody tr', 1)
                                ->assertSubscription(
                                    0,
                                    "Standard conference room",
                                    "Audio & video conference room",
                                    "0,00 CHF/month"
                                )
                                ->assertSubscriptionState(0, true);
                        });
                })
                ->click('@nav li:last-child a')
                ->with('@settings form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Password')
                        ->assertValue('.row:nth-child(1) input', '')
                        ->assertSeeIn('.row:nth-child(2) label', 'Locked room')
                        ->assertNotChecked('.row:nth-child(2) input')
                        ->assertSeeIn('.row:nth-child(3) label', 'Subscribers only')
                        ->assertNotChecked('.row:nth-child(3) input')
                        ->assertMissing('.row:nth-child(4)');
                });

            // Shared room
            $browser->visit('/room/' . $room->id)
                ->on(new RoomInfo())
                ->assertSeeIn('.card-title', "Room: {$room->name}")
                ->assertVisible('@intro p')
                ->assertVisible("@intro a[href=\"/meet/{$room->name}\"]")
                ->assertMissing('button.button-delete')
                ->assertElementsCount('@nav li', 1)
                // Test room settings
                ->assertSeeIn('@nav li:last-child a', 'Settings')
                ->with('@settings form', function ($browser) {
                    $browser->assertSeeIn('.row:nth-child(1) label', 'Password')
                        ->assertValue('.row:nth-child(1) input', 'pass')
                        ->assertSeeIn('.row:nth-child(2) label', 'Locked room')
                        ->assertChecked('.row:nth-child(2) input')
                        ->assertSeeIn('.row:nth-child(3) label', 'Subscribers only')
                        ->assertChecked('.row:nth-child(3) input')
                        ->assertMissing('.row:nth-child(4)')
                        ->type('.row:nth-child(1) input', 'pass123')
                        ->click('.row:nth-child(2) input')
                        ->click('.row:nth-child(3) input');
                })
                ->click('@settings button[type=submit]')
                ->assertToast(Toast::TYPE_SUCCESS, "Room configuration updated successfully.");

            $config = $room->getConfig();

            $this->assertSame('pass123', $config['password']);
            $this->assertSame(false, $config['locked']);
            $this->assertSame(false, $config['nomedia']);
            $this->assertSame(['jack@kolab.org, full'], $config['acl']);

            $browser->click("@intro a[href=\"/meet/shared\"]")
                ->on(new RoomPage('shared'))
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
                ->assertMissing('@setup-form')
                ->waitFor('a.meet-nickname svg.moderator');
        });
    }
}
