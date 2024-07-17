<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Queue as MigratorQueue;
use Tests\BackendsTrait;
use Tests\TestCase;

/**
 * @group slow
 */
class DAVTest extends TestCase
{
    use BackendsTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        MigratorQueue::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        MigratorQueue::truncate();

        parent::tearDown();
    }

    /**
     * Test DAV to DAV migration
     *
     * @group dav
     */
    public function testInitialMigration(): void
    {
        $uri = \config('services.dav.uri');

        $src = new Account(preg_replace('|^[a-z]+://|', 'dav://john%40kolab.org:simple123@', $uri));
        $dst = new Account(preg_replace('|^[a-z]+://|', 'dav://jack%40kolab.org:simple123@', $uri));

        // Initialize accounts
        $this->initAccount($src);
        $this->initAccount($dst);

        // Add some items to the source account
        $this->davAppend($src, 'Calendar', ['event/1.ics', 'event/2.ics'], Engine::TYPE_EVENT);
        $this->davCreateFolder($src, 'DavDataMigrator', Engine::TYPE_CONTACT);
        $this->davCreateFolder($src, 'DavDataMigrator/Test', Engine::TYPE_CONTACT);
        $this->davAppend($src, 'DavDataMigrator/Test', ['contact/1.vcf', 'contact/2.vcf'], Engine::TYPE_CONTACT);

        // Clean up the destination folders structure
        $this->davDeleteFolder($dst, 'DavDataMigrator', Engine::TYPE_CONTACT);
        $this->davDeleteFolder($dst, 'DavDataMigrator/Test', Engine::TYPE_CONTACT);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true, 'type' => 'event,contact']);

        // Assert the destination account
        $dstFolders = $this->davListFolders($dst, Engine::TYPE_CONTACT);
        $this->assertContains('DavDataMigrator', $dstFolders);
        $this->assertContains('DavDataMigrator/Test', $dstFolders);

        // Assert the migrated events
        $dstObjects = $this->davList($dst, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($dstObjects)->keyBy('uid')->all();
        $this->assertCount(2, $events);
        $this->assertSame('Party', $events['abcdef']->summary);
        $this->assertSame('Meeting', $events['123456']->summary);

        // Assert the migrated contacts and contact folders
        $dstObjects = $this->davList($dst, 'DavDataMigrator/Test', Engine::TYPE_CONTACT);
        $contacts = \collect($dstObjects)->keyBy('uid')->all();
        $this->assertCount(2, $contacts);
        $this->assertSame('Jane Doe', $contacts['uid1']->fn);
        $this->assertSame('Jack Strong', $contacts['uid2']->fn);
    }

    /**
     * Test DAV to DAV incremental migration run
     *
     * @group dav
     * @depends testInitialMigration
     */
    public function testIncrementalMigration(): void
    {
        $uri = \config('services.dav.uri');

        $src = new Account(preg_replace('|^[a-z]+://|', 'dav://john%40kolab.org:simple123@', $uri));
        $dst = new Account(preg_replace('|^[a-z]+://|', 'dav://jack%40kolab.org:simple123@', $uri));

        // Add an event and modify another one
        $srcEvents = $this->davList($src, 'Calendar', Engine::TYPE_EVENT);
        $this->davAppend($src, 'Calendar', ['event/3.ics', 'event/1.1.ics'], Engine::TYPE_EVENT);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true, 'type' => Engine::TYPE_EVENT]);

        // Assert the migrated events
        $dstObjects = $this->davList($dst, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($dstObjects)->keyBy('uid')->all();
        $this->assertCount(3, $events);
        $this->assertSame('Party Update', $events['abcdef']->summary);
        $this->assertSame('Meeting', $events['123456']->summary);
        $this->assertSame('Test Summary', $events['aaa-aaa']->summary);

        // TODO: Assert that unmodified objects aren't migrated again
    }
}
