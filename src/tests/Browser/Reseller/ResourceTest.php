<?php

namespace Tests\Browser\Reseller;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\Resource as ResourcePage;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class ResourceTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test resource info page (unauthenticated)
     */
    public function testResourceUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $resource = $this->getTestResource('resource-test1@kolab.org');

            $browser->visit('/resource/' . $resource->id)->on(new Home());
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
            $resource = $this->getTestResource('resource-test1@kolab.org');
            $resource->setSetting('invitation_policy', 'accept');

            $resource_page = new ResourcePage($resource->id);
            $user_page = new UserPage($user->id);

            // Goto the distlist page
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), \App\Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->visit($user_page)
                ->on($user_page)
                ->click('@nav #tab-resources')
                ->pause(1000)
                ->click('@user-resources table tbody tr:first-child td:first-child a')
                ->on($resource_page)
                ->assertSeeIn('@resource-info .card-title', $resource->email)
                ->with('@resource-info form', function (Browser $browser) use ($resource) {
                    $browser->assertElementsCount('.row', 3)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(1) #resourceid', "{$resource->id} ({$resource->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(3) label', 'Name')
                        ->assertSeeIn('.row:nth-child(3) #name', $resource->name);
                })
                ->assertElementsCount('ul.nav-tabs', 1)
                ->assertSeeIn('ul.nav-tabs .nav-link', 'Settings')
                ->with('@resource-settings form', function (Browser $browser) {
                    $browser->assertElementsCount('.row', 1)
                        ->assertSeeIn('.row:nth-child(1) label', 'Invitation policy')
                        ->assertSeeIn('.row:nth-child(1) #invitation_policy', 'accept');
                });

            // Test invalid resource identifier
            $browser->visit('/resource/abc')->assertErrorPage(404);
        });
    }
}
