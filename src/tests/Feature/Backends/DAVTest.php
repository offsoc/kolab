<?php

namespace Tests\Feature\Backends;

use App\Backends\DAV;
use App\Backends\IMAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DAVTest extends TestCase
{
    private $user;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!\config('services.dav.uri')) {
            $this->markTestSkipped();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if ($this->user) {
            $this->deleteTestUser($this->user->email);
        }

        parent::tearDown();
    }

    /**
     * Test initializing default folders for a user.
     *
     * @group imap
     * @group dav
     */
    public function testInitDefaultFolders(): void
    {
        Queue::fake();

        $props = ['password' => 'test-pass'];
        $this->user = $user = $this->getTestUser('davtest-' . time() . '@' . \config('app.domain'), $props);

        // Create the IMAP mailbox, it is required otherwise DAV requests will fail
        \config(['services.imap.default_folders' => null]);
        IMAP::createUser($user);

        $dav_folders = [
            [
                'path' => 'Default',
                'displayname' => 'Calendar-Test',
                'components' => ['VEVENT'],
                'type' => 'calendar',
            ],
            [
                'path' => 'Tasks',
                'displayname' => 'Tasks-Test',
                'components' => ['VTODO'],
                'type' => 'calendar',
            ],
            [
                'path' => 'Default',
                'displayname' => 'Contacts-Test',
                'type' => 'addressbook',
            ],
        ];

        \config(['services.dav.default_folders' => $dav_folders]);
        DAV::initDefaultFolders($user);

        $dav = new DAV($user->email, $props['password']);

        $folders = $dav->listFolders(DAV::TYPE_VCARD);
        $this->assertCount(1, $folders);
        $this->assertSame('Contacts-Test', $folders[0]->name);

        $folders = $dav->listFolders(DAV::TYPE_VEVENT);
        $folders = array_filter($folders, function ($f) {
            return $f->name != 'Inbox' && $f->name != 'Outbox';
        });
        $folders = array_values($folders);
        $this->assertCount(1, $folders);
        $this->assertSame(['VEVENT'], $folders[0]->components);
        $this->assertSame(['collection', 'calendar'], $folders[0]->types);
        $this->assertSame('Calendar-Test', $folders[0]->name);

        $folders = $dav->listFolders(DAV::TYPE_VTODO);
        $folders = array_filter($folders, function ($f) {
            return $f->name != 'Inbox' && $f->name != 'Outbox';
        });
        $folders = array_values($folders);
        $this->assertCount(1, $folders);
        $this->assertSame(['VTODO'], $folders[0]->components);
        $this->assertSame(['collection', 'calendar'], $folders[0]->types);
        $this->assertSame('Tasks-Test', $folders[0]->name);
    }
}
