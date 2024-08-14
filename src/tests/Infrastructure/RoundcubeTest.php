<?php

namespace Tests\Infrastructure;

use Tests\Browser;
use Tests\TestCaseDusk;

class RoundcubeTest extends TestCaseDusk
{
    private static ?\App\User $user = null;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        Browser::$baseUrl = \config("services.webmail.uri");

        if (!self::$user) {
            self::$user = $this->getTestUser('roundcubetesttest@kolab.org', ['password' => 'simple123'], true);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testLogin()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->type('#rcmloginuser', self::$user->email)
                ->type('#rcmloginpwd', 'simple123')
                ->press('#rcmloginsubmit')
                ->waitFor('#logo')
                ->waitUntil('!rcmail.busy')
                ->assertSee('Inbox');

            $browser->press('.contacts')
                ->waitUntil('!rcmail.busy')
                ->assertVisible('#directorylist')
                ->assertVisible('.addressbook.personal')
                ->assertSee('Addressbook');

            $browser->press('.button-calendar')
                ->waitUntil('!rcmail.busy')
                ->assertSee('Calendar');

            //TODO requires the default folders to be created
            // $browser->press('.button-files')
            // ->waitUntil('!rcmail.busy')
            // ->assertSeeIn('#files-folder-list', 'Files');

            $browser->press('.button-tasklist')
                ->waitUntil('!rcmail.busy')
                ->assertSee('Calendar'); // TODO: It will be 'Tasks' at some point

            $browser->press('.settings')
                ->waitUntil('!rcmail.busy')
                ->assertSee('Activesync');
        });
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
