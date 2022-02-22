<?php

namespace Tests\Browser\Admin;

use App\SharedFolder;
use Illuminate\Support\Facades\Queue;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\SharedFolder as SharedFolderPage;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class SharedFolderTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test shared folder info page (unauthenticated)
     */
    public function testSharedFolderUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $folder = $this->getTestSharedFolder('folder-event@kolab.org');

            $browser->visit('/shared-folder/' . $folder->id)->on(new Home());
        });
    }

    /**
     * Test shared folder info page
     */
    public function testInfo(): void
    {
        Queue::fake();

        $this->browse(function (Browser $browser) {
            $user = $this->getTestUser('john@kolab.org');
            $folder = $this->getTestSharedFolder('folder-event@kolab.org');
            $folder->setConfig(['acl' => ['anyone, read-only', 'jack@kolab.org, read-write']]);
            $folder->setAliases(['folder-alias1@kolab.org', 'folder-alias2@kolab.org']);
            $folder->status = SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE
                | SharedFolder::STATUS_LDAP_READY | SharedFolder::STATUS_IMAP_READY;
            $folder->save();

            $folder_page = new SharedFolderPage($folder->id);
            $user_page = new UserPage($user->id);

            // Goto the folder page
            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', \App\Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->visit($user_page)
                ->on($user_page)
                ->click('@nav #tab-shared-folders')
                ->pause(1000)
                ->click('@user-shared-folders table tbody tr:first-child td:first-child a')
                ->on($folder_page)
                ->assertSeeIn('@folder-info .card-title', $folder->email)
                ->with('@folder-info form', function (Browser $browser) use ($folder) {
                    $browser->assertElementsCount('.row', 4)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created)')
                        ->assertSeeIn('.row:nth-child(1) #folderid', "{$folder->id} ({$folder->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(3) label', 'Name')
                        ->assertSeeIn('.row:nth-child(3) #name', $folder->name)
                        ->assertSeeIn('.row:nth-child(4) label', 'Type')
                        ->assertSeeIn('.row:nth-child(4) #type', 'Calendar');
                })
                ->assertElementsCount('ul.nav-tabs .nav-item', 2)
                ->assertSeeIn('ul.nav-tabs .nav-item:nth-child(1) .nav-link', 'Settings')
                ->with('@folder-settings form', function (Browser $browser) {
                    $browser->assertElementsCount('.row', 1)
                        ->assertSeeIn('.row:nth-child(1) label', 'Access rights')
                        ->assertSeeIn('.row:nth-child(1) #acl', 'anyone: read-only')
                        ->assertSeeIn('.row:nth-child(1) #acl', 'jack@kolab.org: read-write');
                })
                ->assertSeeIn('ul.nav-tabs .nav-item:nth-child(2) .nav-link', 'Email Aliases (2)')
                ->click('ul.nav-tabs .nav-item:nth-child(2) .nav-link')
                ->with('@folder-aliases table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 2)
                        ->assertSeeIn('tbody tr:nth-child(1) td', 'folder-alias1@kolab.org')
                        ->assertSeeIn('tbody tr:nth-child(2) td', 'folder-alias2@kolab.org');
                });

            // Test invalid shared folder identifier
            $browser->visit('/shared-folder/abc')->assertErrorPage(404);
        });
    }
}
