<?php

namespace Tests\Browser\Admin;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\Distlist as DistlistPage;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class DistlistTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $this->deleteTestGroup('group-test@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolab.org');

        parent::tearDown();
    }

    /**
     * Test distlist info page (unauthenticated)
     */
    public function testDistlistUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $group = $this->getTestGroup('group-test@kolab.org');
            $group->assignToWallet($user->wallets->first());

            $browser->visit('/distlist/' . $group->id)->on(new Home());
        });
    }

    /**
     * Test distribution list info page
     */
    public function testInfo(): void
    {
        Queue::fake();

        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $group = $this->getTestGroup('group-test@kolab.org');
            $group->assignToWallet($user->wallets->first());
            $group->members = ['test1@gmail.com', 'test2@gmail.com'];
            $group->save();

            $distlist_page = new DistlistPage($group->id);
            $user_page = new UserPage($user->id);

            // Goto the distlist page
            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', \App\Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->visit($user_page)
                ->on($user_page)
                ->click('@nav #tab-distlists')
                ->pause(1000)
                ->click('@user-distlists table tbody tr:first-child td a')
                ->on($distlist_page)
                ->assertSeeIn('@distlist-info .card-title', $group->email)
                ->with('@distlist-info form', function (Browser $browser) use ($group) {
                    $browser->assertElementsCount('.row', 3)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(1) #distlistid', "{$group->id} ({$group->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status.text-danger', 'Not Ready')
                        ->assertSeeIn('.row:nth-child(3) label', 'Recipients')
                        ->assertSeeIn('.row:nth-child(3) #members', $group->members[0])
                        ->assertSeeIn('.row:nth-child(3) #members', $group->members[1]);
                });

            // Test invalid group identifier
            $browser->visit('/distlist/abc')->assertErrorPage(404);
        });
    }

    /**
     * Test suspending/unsuspending a distribution list
     *
     * @depends testInfo
     */
    public function testSuspendAndUnsuspend(): void
    {
        Queue::fake();

        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $group = $this->getTestGroup('group-test@kolab.org');
            $group->assignToWallet($user->wallets->first());
            $group->status = Group::STATUS_ACTIVE | Group::STATUS_LDAP_READY;
            $group->save();

            $browser->visit(new DistlistPage($group->id))
                ->assertVisible('@distlist-info #button-suspend')
                ->assertMissing('@distlist-info #button-unsuspend')
                ->assertSeeIn('@distlist-info #status.text-success', 'Active')
                ->click('@distlist-info #button-suspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'Distribution list suspended successfully.')
                ->assertSeeIn('@distlist-info #status.text-warning', 'Suspended')
                ->assertMissing('@distlist-info #button-suspend')
                ->click('@distlist-info #button-unsuspend')
                ->assertToast(Toast::TYPE_SUCCESS, 'Distribution list unsuspended successfully.')
                ->assertSeeIn('@distlist-info #status.text-success', 'Active')
                ->assertVisible('@distlist-info #button-suspend')
                ->assertMissing('@distlist-info #button-unsuspend');
        });
    }
}
