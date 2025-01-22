<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Driver\Kolab\Tags as KolabTags;
use App\DataMigrator\Engine;
use App\DataMigrator\Queue as MigratorQueue;
use Tests\BackendsTrait;
use Tests\TestCase;

/**
 * @group slow
 * @group dav
 * @group imap
 */
class KolabTest extends TestCase
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
     * Test Kolab3 to Kolab4 migration
     */
    public function testInitialMigration3to4(): void
    {
        [$src, $src_imap, $src_dav, $dst, $dst_imap, $dst_dav] = $this->getTestAccounts();

        $this->prepareKolab3Account($src_imap, $src_dav);
        $this->prepareKolab4Account($dst_imap, $dst_dav);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // Assert the migrated mail
        $messages = $this->imapList($dst_imap, 'INBOX');
        $messages = \collect($messages)->keyBy('messageID')->all();
        $this->assertCount(2, $messages);
        $this->assertSame([], $messages['<sync1@kolab.org>']->flags);
        $this->assertSame(['SEEN'], array_keys($messages['<sync2@kolab.org>']->flags));
        $sync1_uid = $messages['<sync1@kolab.org>']->uid;
        $sync2_uid = $messages['<sync2@kolab.org>']->uid;

        $messages = $this->imapList($dst_imap, 'Drafts');
        $messages = \collect($messages)->keyBy('messageID')->all();
        $this->assertCount(1, $messages);
        $this->assertSame(['SEEN'], array_keys($messages['<sync3@kolab.org>']->flags));

        $this->assertCount(0, $this->imapList($dst_imap, 'Spam'));
        $this->assertCount(0, $this->imapList($dst_imap, 'Trash'));

        // Assert non-mail folders were not migrated
        $mail_folders = $this->imapListFolders($dst_imap);
        $this->assertNotContains('Configuration', $mail_folders);
        $this->assertNotContains('Test', $mail_folders);

        // Extra IMAP folder with non-ascii characters
        $utf7_folder = \mb_convert_encoding('&kość', 'UTF7-IMAP', 'UTF8');
        $this->assertContains($utf7_folder, $mail_folders);

        // Check migration of folder subscription state
        $subscribed = $this->imapListFolders($dst_imap, true);
        $this->assertNotContains($utf7_folder, $subscribed);
        $this->assertContains('Test2', $subscribed);

        // Assert migrated tags
        $imap = $this->getImapClient($dst_imap);
        $tags = KolabTags::getKolab4Tags($imap);
        $this->assertCount(1, $tags);
        $this->assertSame('tag', $tags[0]['name']);
        $this->assertSame('#E0431B', $tags[0]['color']);
        $members = KolabTags::getKolab4TagMembers($imap, 'tag', 'INBOX');
        $this->assertSame([(string) $sync1_uid, (string) $sync2_uid], $members);

        // Assert the migrated events
        $events = $this->davList($dst_dav, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(2, $events);
        $this->assertSame('Party', $events['abcdef']->summary);
        $this->assertSame('Meeting', $events['123456']->summary);

        $events = $this->davList($dst_dav, 'Custom Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(1, $events);
        $this->assertSame('Test Summary', $events['aaa-aaa']->summary);

        // Assert the migrated contacts
        $contacts = $this->davList($dst_dav, 'Contacts', Engine::TYPE_CONTACT);
        $contacts = \collect($contacts)->keyBy('uid')->all();
        $this->assertCount(2, $contacts);
        $this->assertSame('Jane Doe', $contacts['uid1']->fn);
        $this->assertSame('Jack Strong', $contacts['uid2']->fn);

        // Assert the migrated tasks
        $tasks = $this->davList($dst_dav, 'Tasks', Engine::TYPE_TASK);
        $tasks = \collect($tasks)->keyBy('uid')->all();
        $this->assertCount(2, $tasks);
        $this->assertSame('Task1', $tasks['ccc-ccc']->summary);
        $this->assertSame('Task2', $tasks['ddd-ddd']->summary);
    }

    /**
     * Test Kolab3 to Kolab4 incremental migration run
     *
     * @depends testInitialMigration3to4
     */
    public function testIncrementalMigration3to4(): void
    {
        [$src, $src_imap, $src_dav, $dst, $dst_imap, $dst_dav] = $this->getTestAccounts();

        // Add/modify some source account data
        $messages = $this->imapList($src_imap, 'INBOX');
        $existing = \collect($messages)->keyBy('messageID')->all();
        $this->imapFlagAs($src_imap, 'INBOX', $existing['<sync1@kolab.org>']->uid, ['SEEN', 'FLAGGED']);
        $this->imapFlagAs($src_imap, 'INBOX', $existing['<sync2@kolab.org>']->uid, ['UNSEEN']);
        $this->imapAppend($src_imap, 'Configuration', 'kolab3/tag2.eml');
        $this->imapAppend($src_imap, 'Drafts', 'mail/4.eml', ['SEEN']);
        $replace = [
            // '/john@kolab.org/' => 'ned@kolab.org',
            '/DTSTAMP:19970714T170000Z/' => 'DTSTAMP:20240714T170000Z',
            '/SUMMARY:Party/' => 'SUMMARY:Test'
        ];
        $this->davAppend($src_dav, 'Calendar', ['event/1.ics'], Engine::TYPE_EVENT, $replace);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true,'sync' => true]);

        // Assert the migrated mail
        $messages = $this->imapList($dst_imap, 'INBOX');
        $messages = \collect($messages)->keyBy('messageID')->all();
        $this->assertCount(2, $messages);
        $this->assertSame(['FLAGGED', 'SEEN'], array_keys($messages['<sync1@kolab.org>']->flags));
        $this->assertSame([], $messages['<sync2@kolab.org>']->flags);
        $sync2_uid = $messages['<sync2@kolab.org>']->uid;

        $messages = $this->imapList($dst_imap, 'Drafts');
        $messages = \collect($messages)->keyBy('messageID')->all();
        $this->assertCount(2, $messages);
        $this->assertSame(['SEEN'], array_keys($messages['<sync3@kolab.org>']->flags));
        $this->assertSame(['SEEN'], array_keys($messages['<sync4@kolab.org>']->flags));
        $sync3_uid = $messages['<sync3@kolab.org>']->uid;
        $sync4_uid = $messages['<sync4@kolab.org>']->uid;

        // Assert migrated tags
        $imap = $this->getImapClient($dst_imap);
        $tags = KolabTags::getKolab4Tags($imap);
        $this->assertCount(2, $tags);
        $this->assertSame('tag', $tags[0]['name']);
        $this->assertSame('#E0431B', $tags[0]['color']);
        $this->assertSame('test', $tags[1]['name']);
        $this->assertSame('#FFFFFF', $tags[1]['color']);
        $members = KolabTags::getKolab4TagMembers($imap, 'test', 'INBOX');
        $this->assertSame([(string) $sync2_uid], $members);
        $members = KolabTags::getKolab4TagMembers($imap, 'test', 'Drafts');
        $this->assertSame([(string) $sync3_uid, (string) $sync4_uid], $members);

        // Assert the migrated events
        $events = $this->davList($dst_dav, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(2, $events);
        $this->assertSame('Test', $events['abcdef']->summary);
    }

    /**
     * Initialize accounts for tests
     */
    private function getTestAccounts()
    {
        $dav_uri = \config('services.dav.uri');
        $dav_uri = preg_replace('|^http|', 'dav', $dav_uri);
        $imap_uri = \config('services.imap.uri');
        if (strpos($imap_uri, '://') === false) {
            $imap_uri = 'imap://' . $imap_uri;
        }

        $kolab3_uri = preg_replace('|^[a-z]+://|', 'kolab3://ned%40kolab.org:simple123@', $imap_uri)
            . '?dav_host=' . preg_replace('|^davs?://|', '', $dav_uri);
        $kolab4_uri = preg_replace('|^[a-z]+://|', 'kolab4://jack%40kolab.org:simple123@', $imap_uri)
            . '?dav_host=' . preg_replace('|^davs?://|', '', $dav_uri);

        // Note: These are Kolab4 accounts, we'll modify the src account to imitate a Kolab3 account
        // as much as we can. See self::prepareKolab3Account()

        $src_imap = new Account(preg_replace('|://|', '://ned%40kolab.org:simple123@', $imap_uri));
        $src_dav = new Account(preg_replace('|://|', '://ned%40kolab.org:simple123@', $dav_uri));
        $src = new Account($kolab3_uri);
        $dst_imap = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $imap_uri));
        $dst_dav = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $dav_uri));
        $dst = new Account($kolab4_uri);

        return [$src, $src_imap, $src_dav, $dst, $dst_imap, $dst_dav];
    }

    /**
     * Initial preparation of a Kolab v3 account for tests
     */
    private function prepareKolab3Account(Account $imap_account, Account $dav_account)
    {
        // Cleanup the account
        $this->initAccount($imap_account);
        $this->initAccount($dav_account);
        $this->davDeleteFolder($dav_account, 'Custom Calendar', Engine::TYPE_EVENT);

        $imap = $this->getImapClient($imap_account);

        // Create Configuration folder with sample relation (tag) object
        $this->imapCreateFolder($imap_account, 'Configuration');
        $this->imapEmptyFolder($imap_account, 'Configuration');
        if (!$imap->setMetadata('Configuration', ['/private/vendor/kolab/folder-type' => 'configuration'])) {
            throw new \Exception("Failed to set metadata");
        }
        $this->imapAppend($imap_account, 'Configuration', 'kolab3/tag1.eml');
        $this->imapAppend($imap_account, 'Configuration', 'kolab3/tag2.eml', ['DELETED']);

        // Create a non-mail folder, we'll assert that it was skipped in migration
        $this->imapCreateFolder($imap_account, 'Test');
        if (!$imap->setMetadata('Test', ['/private/vendor/kolab/folder-type' => 'contact'])) {
            throw new \Exception("Failed to set metadata");
        }
        if (!$imap->setMetadata('INBOX', ['/private/vendor/kolab/folder-type' => 'mail.inbox'])) {
            throw new \Exception("Failed to set metadata");
        }

        // Create an IMAP folder with non-ascii characters, unsubscribed
        $this->imapCreateFolder($imap_account, \mb_convert_encoding('&kość', 'UTF7-IMAP', 'UTF8'));

        // One more IMAP folder, subscribed
        $this->imapCreateFolder($imap_account, 'Test2', true);

        // Insert some other data to migrate
        $this->imapAppend($imap_account, 'INBOX', 'mail/1.eml');
        $this->imapAppend($imap_account, 'INBOX', 'mail/2.eml', ['SEEN']);
        $this->imapAppend($imap_account, 'Drafts', 'mail/3.eml', ['SEEN']);
        $this->davAppend($dav_account, 'Calendar', ['event/1.ics', 'event/2.ics'], Engine::TYPE_EVENT);
        $this->davCreateFolder($dav_account, 'Custom Calendar', Engine::TYPE_EVENT);
        $this->davAppend($dav_account, 'Custom Calendar', ['event/3.ics'], Engine::TYPE_EVENT);
        $this->davAppend($dav_account, 'Contacts', ['contact/1.vcf', 'contact/2.vcf'], Engine::TYPE_CONTACT);
        $this->davAppend($dav_account, 'Tasks', ['task/1.ics', 'task/2.ics'], Engine::TYPE_TASK);
    }

    /**
     * Initial preparation of a Kolab v4 account for tests
     */
    private function prepareKolab4Account(Account $imap_account, Account $dav_account)
    {
        $this->initAccount($imap_account);
        $this->initAccount($dav_account);
        $this->davDeleteFolder($dav_account, 'Custom Calendar', Engine::TYPE_EVENT);
        $this->imapDeleteFolder($imap_account, 'Test');
        $this->imapDeleteFolder($imap_account, 'Test2');
        $this->imapDeleteFolder($imap_account, \mb_convert_encoding('&kość', 'UTF7-IMAP', 'UTF8'));
        $this->imapDeleteFolder($imap_account, 'Configuration');
        $imap = $this->getImapClient($imap_account);
        KolabTags::saveKolab4Tags($imap, []);
    }
}
