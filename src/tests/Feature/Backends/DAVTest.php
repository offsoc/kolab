<?php

namespace Tests\Feature\Backends;

use App\Backends\DAV;
use App\Backends\IMAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DAVTest extends TestCase
{
    private $user;
    private $user2;

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
            $this->deleteTestUser($this->user->email, true);
        }
        if ($this->user2) {
            $this->deleteTestUser($this->user2->email);
        }

        parent::tearDown();
    }
    /**
     * Test initializing default folders for a user.
     *
     * @group imap
     * @group dav
     */
    public function testInitDefaultFolders(): array
    {
        Queue::fake();

        $ts = str_replace('.', '', (string) microtime(true));
        $user = $this->getTestUser(
            "davtest-{$ts}@" . \config('app.domain'),
            $props = ['password' => 'test-pass']
        );

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

        // Create the IMAP mailbox, it is required otherwise DAV requests will fail
        \config(['services.imap.default_folders' => null]);
        \config(['services.dav.default_folders' => $dav_folders]);
        IMAP::createUser($user);
        DAV::initDefaultFolders($user);

        $dav = DAV::getInstance($user->email, $props['password']);

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

        return [$dav_folders, $user];
    }

    /**
     * Test sharing/unsharing folders for a user (delegation).
     *
     * @depends testInitDefaultFolders
     * @group imap
     * @group dav
     */
    public function testShareAndUnshareFolders($args): void
    {
        Queue::fake();

        $ts = str_replace('.', '', (string) microtime(true));
        $this->user2 = $user2 = $this->getTestUser(
            "davtest2-{$ts}@" . \config('app.domain'),
            $props = ['password' => 'test-pass']
        );
        $this->user = $user = $args[1];
        $dav_folders = $args[0];

        // Create the IMAP mailbox, it is required otherwise DAV requests will fail
        \config(['services.imap.default_folders' => null]);
        \config(['services.dav.default_folders' => $dav_folders]);
        IMAP::createUser($user2);
        DAV::initDefaultFolders($user2);

        // Test delegation of calendar and addressbook folders only
        DAV::shareDefaultFolders($user, $user2, ['event' => 'read-only', 'contact' => 'read-write']);

        $dav = DAV::getInstance($user2->email, $props['password']);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VCARD),
            fn ($folder) => $folder->owner === $user->email
        ));
        $this->assertCount(1, $folders);
        $this->assertSame('read-write', $folders[0]->shareAccess);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VEVENT),
            fn ($folder) => $folder->owner === $user->email
        ));
        $this->assertCount(1, $folders);
        $this->assertSame('read', $folders[0]->shareAccess);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VTODO),
            fn ($folder) => $folder->owner === $user->email
        ));
        $this->assertCount(0, $folders);

        // Test unsubscribing from other user folders
        DAV::unsubscribeSharedFolders($user2, $user->email);

        $dav = DAV::getInstance($user2->email, $props['password']);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VCARD),
            fn ($folder) => $folder->owner === $user->email
        ));
        $this->assertCount(0, $folders);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VEVENT),
            fn ($folder) => $folder->owner === $user->email
        ));
        $this->assertCount(0, $folders);

        // Test unsharing folders
        DAV::unshareFolders($user, $user2->email);

        $dav = DAV::getInstance($user->email, $props['password']);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VCARD),
            fn ($folder) => $folder->owner != $user->email
                || $folder->shareAccess != DAV\Folder::SHARE_ACCESS_NONE
                || !empty($folder->invites)
        ));
        $this->assertCount(0, $folders);

        $folders = array_values(array_filter(
            $dav->listFolders(DAV::TYPE_VEVENT),
            fn ($folder) => $folder->owner != $user->email
                || $folder->shareAccess != DAV\Folder::SHARE_ACCESS_NONE
                || !empty($folder->invites)
        ));
        $this->assertCount(0, $folders);
    }
}
