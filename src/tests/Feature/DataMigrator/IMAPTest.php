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
        $utf7_folder = \mb_convert_encoding('ImapDataMigrator/&kość', 'UTF7-IMAP', 'UTF8');
        $this->imapAppend($src, 'INBOX', 'mail/1.eml');
        $this->imapAppend($src, 'INBOX', 'mail/2.eml', ['SEEN']);
        $this->imapCreateFolder($src, 'ImapDataMigrator');
        $this->imapCreateFolder($src, 'ImapDataMigrator/Test');
        $this->imapCreateFolder($src, $utf7_folder, true);
        $this->imapAppend($src, $utf7_folder, 'mail/1.eml', [], '10-Jan-2024 09:09:09 +0000');
        $this->imapAppend($src, $utf7_folder, 'mail/2.eml', [], '10-Jan-2024 09:09:09 +0000');

        // Clean up the destination folders structure
        $this->imapDeleteFolder($dst, $utf7_folder);
        $this->imapDeleteFolder($dst, 'ImapDataMigrator/Test');
        $this->imapDeleteFolder($dst, 'ImapDataMigrator');

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // Assert the destination mailbox
        $dstFolders = $this->imapListFolders($dst);
        $this->assertContains('ImapDataMigrator', $dstFolders);
        $this->assertContains('ImapDataMigrator/Test', $dstFolders);
        $this->assertContains($utf7_folder, $dstFolders);
        $subscribed = $this->imapListFolders($dst, true);
        $this->assertContains($utf7_folder, $subscribed);
        $this->assertNotContains('ImapDataMigrator/Test', $subscribed);

        // Assert the migrated messages
        $dstMessages = $this->imapList($dst, 'INBOX');
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));

        $dstMessages = $this->imapList($dst, $utf7_folder);
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $this->assertSame('10-Jan-2024 09:09:09 +0000', $msg->internaldate);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $this->assertSame('10-Jan-2024 09:09:09 +0000', $msg->internaldate);
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
        $utf7_folder = \mb_convert_encoding('ImapDataMigrator/&kość', 'UTF7-IMAP', 'UTF8');
        $dstMessages = $this->imapList($dst, $utf7_folder);
        $this->assertCount(2, $dstMessages);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync1@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        $msg = array_shift($dstMessages);
        $this->assertSame('<sync2@kolab.org>', $msg->messageID);
        $this->assertSame([], $msg->flags);
    }
}
