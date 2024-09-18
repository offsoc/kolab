<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Queue as MigratorQueue;
use Tests\BackendsTrait;
use Tests\TestCase;

class IMAPTest extends TestCase
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
     * Test IMAP to IMAP migration
     *
     * @group imap
     */
    public function testInitialMigration(): void
    {
        $uri = \config('services.imap.uri');

        if (strpos($uri, '://') === false) {
            $uri = 'imap://' . $uri;
        }

        $src = new Account(str_replace('://', '://john%40kolab.org:simple123@', $uri));
        $dst = new Account(str_replace('://', '://jack%40kolab.org:simple123@', $uri));

        // Initialize accounts
        $this->initAccount($src);
        $this->initAccount($dst);

        // Add some mail to the source account
        $this->imapAppend($src, 'INBOX', 'mail/1.eml');
        $this->imapAppend($src, 'INBOX', 'mail/2.eml', ['SEEN']);
        $this->imapCreateFolder($src, 'ImapDataMigrator');
        $this->imapCreateFolder($src, 'ImapDataMigrator/Test');
        $this->imapAppend($src, 'ImapDataMigrator/Test', 'mail/1.eml');
        $this->imapAppend($src, 'ImapDataMigrator/Test', 'mail/2.eml');

        // Clean up the destination folders structure
        $this->imapDeleteFolder($dst, 'ImapDataMigrator/Test');
        $this->imapDeleteFolder($dst, 'ImapDataMigrator');

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // Assert the destination mailbox
        $dstFolders = $this->imapListFolders($dst);
        $this->assertContains('ImapDataMigrator', $dstFolders);
        $this->assertContains('ImapDataMigrator/Test', $dstFolders);

        // Assert the migrated messages
        $dstMessages = $this->imapList($dst, 'INBOX');
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));

        $dstMessages = $this->imapList($dst, 'ImapDataMigrator/Test');
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);

        // TODO: Test INTERNALDATE migration
    }

    /**
     * Test IMAP to IMAP incremental migration run
     *
     * @group imap
     * @depends testInitialMigration
     */
    public function testIncrementalMigration(): void
    {
        $uri = \config('services.imap.uri');

        if (strpos($uri, '://') === false) {
            $uri = 'imap://' . $uri;
        }

        // Let's test with impersonation now
        $adminUser = \config('services.imap.admin_login');
        $adminPass = \config('services.imap.admin_password');
        $src = new Account(str_replace('://', "://$adminUser:$adminPass@", $uri) . '?user=john%40kolab.org');
        $dst = new Account(str_replace('://', "://$adminUser:$adminPass@", $uri) . '?user=jack%40kolab.org');

        // Add some mails to the source account
        $srcMessages = $this->imapList($src, 'INBOX');
        $msg1 = array_shift($srcMessages);
        $msg2 = array_shift($srcMessages);
        $this->imapAppend($src, 'INBOX', 'mail/3.eml');
        $this->imapAppend($src, 'INBOX', 'mail/4.eml');
        $this->imapFlagAs($src, 'INBOX', $msg1->uid, ['SEEN']);
        $this->imapFlagAs($src, 'INBOX', $msg2->uid, ['UNSEEN', 'FLAGGED']);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // In INBOX two new messages and two old ones with changed flags
        // The order of messages tells us that there was no redundant APPEND+DELETE
        $dstMessages = $this->imapList($dst, 'INBOX');
        $this->assertCount(4, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame(['FLAGGED'], array_keys($msg->flags));
        $ids = array_map(fn ($msg) => $msg->messageID, $dstMessages);
        $this->assertSame(['<sync3@kolab.org>','<sync4@kolab.org>'], $ids);

        // Nothing changed in the other folder
        $dstMessages = $this->imapList($dst, 'ImapDataMigrator/Test');
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
    }
}
