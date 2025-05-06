<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Queue as MigratorQueue;
use Tests\BackendsTrait;
use Tests\TestCase;

/**
 * @group slow
 * @group dav
 * @group imap
 */
class TakeoutTest extends TestCase
{
    use BackendsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        MigratorQueue::truncate();
    }

    protected function tearDown(): void
    {
        MigratorQueue::truncate();

        exec('rm -rf ' . storage_path('export/test@gmail.com'));

        parent::tearDown();
    }

    /**
     * Test Google Takeout to Kolab migration
     */
    public function testInitialMigration(): void
    {
        [$imap, $dav, $src, $dst] = $this->getTestAccounts();

        // Cleanup the Kolab account
        $this->initAccount($imap);
        $this->initAccount($dav);
        $this->davDeleteFolder($dav, 'Custom Calendar', Engine::TYPE_EVENT);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // Assert the migrated mail
        $messages = $this->imapList($imap, 'INBOX');
        $this->assertCount(3, $messages);
        $msg = array_shift($messages);
        $this->assertSame('<1@google.com>', $msg->messageID);
        $this->assertSame([], $msg->flags);
        // Note: Cyrus returns INTERNALDATE in UTC even though we used different TZ in APPEND
        $this->assertSame(' 8-Jun-2024 13:37:46 +0000', $msg->internaldate);
        $msg = array_shift($messages);
        $this->assertSame('<3@google.com>', $msg->messageID);
        $this->assertSame(['FLAGGED'], array_keys($msg->flags));
        $this->assertSame('30-Jun-2022 17:45:58 +0000', $msg->internaldate);
        $msg = array_shift($messages);
        $this->assertSame('<5@google.com>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));
        $this->assertSame('30-Jun-2022 19:45:58 +0000', $msg->internaldate);

        $messages = $this->imapList($imap, 'Sent');
        $this->assertCount(1, $messages);
        $msg = array_shift($messages);
        $this->assertSame('<4@google.com>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));

        $messages = $this->imapList($imap, 'Drafts');
        $this->assertCount(2, $messages);
        $msg = array_shift($messages);
        $this->assertSame('<6@google.com>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));
        $msg = array_shift($messages);
        $this->assertSame('<7@google.com>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));

        $messages = $this->imapList($imap, 'Spam');
        $this->assertCount(0, $messages);

        $messages = $this->imapList($imap, 'Trash');
        $this->assertCount(1, $messages);
        $msg = array_shift($messages);
        $this->assertSame('<2@google.com>', $msg->messageID);
        $this->assertSame(['SEEN'], array_keys($msg->flags));

        // Assert the migrated events
        $events = $this->davList($dav, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(4, $events);
        $this->assertSame('testt', $events['m3gkk34n6t7spu6b8li4hvraug@google.com']->summary);
        $this->assertSame('TestX', $events['mpm0q3ki8plp8d7s3uagag989k@google.com']->summary);
        $this->assertSame('ssss', $events['44gvk1rth37n1qk8nsml26o7og@google.com']->summary);
        $this->assertSame('recur', $events['0o2o2fnfdajjsnt2dnt50vckpf@google.com']->summary);
        $this->assertCount(1, $events['0o2o2fnfdajjsnt2dnt50vckpf@google.com']->exceptions);

        $events = $this->davList($dav, 'Custom Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(1, $events);
        $this->assertSame('TestY', $events['ps1tmklc3gvao@google.com']->summary);

        // Assert the migrated contacts
        // Note: Contacts do not have UID in Takeout so it's generated
        $contacts = $this->davList($dav, 'Contacts', Engine::TYPE_CONTACT);
        $contacts = \collect($contacts)->keyBy('fn')->all();
        $this->assertCount(2, $contacts);
        $this->assertSame('test note', $contacts['Test']->note);
        $this->assertSame('erwe note', $contacts['Nameof']->note);
        $this->assertTrue(strlen($contacts['Nameof']->photo) == 5630);
    }

    /**
     * Test Google Takeout to Kolab incremental migration run
     *
     * @depends testInitialMigration
     */
    public function testIncrementalMigration(): void
    {
        [$imap, $dav, $src, $dst] = $this->getTestAccounts();

        // We modify a some mail messages and single event to make sure they get updated/overwritten by migration
        $messages = $this->imapList($imap, 'INBOX');
        $existing = \collect($messages)->keyBy('messageID')->all();
        $this->imapFlagAs($imap, 'INBOX', $existing['<1@google.com>']->uid, ['SEEN', 'FLAGGED']);
        $this->imapFlagAs($imap, 'INBOX', $existing['<5@google.com>']->uid, ['UNSEEN']);
        $this->imapEmptyFolder($imap, 'Sent');
        $this->davEmptyFolder($dav, 'Custom Calendar', Engine::TYPE_EVENT);
        $replace = [
            '/UID:aaa-aaa/' => 'UID:44gvk1rth37n1qk8nsml26o7og@google.com',
            '/john@kolab.org/' => 'test@gmail.com',
        ];
        $this->davAppend($dav, 'Calendar', ['event/3.ics'], Engine::TYPE_EVENT, $replace);

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, ['force' => true, 'sync' => true]);

        // Assert the migrated mail
        $messages = $this->imapList($imap, 'INBOX');
        $messages = \collect($messages)->keyBy('messageID')->all();
        $this->assertCount(3, $messages);
        $this->assertSame([], $messages['<1@google.com>']->flags);
        $this->assertSame(['FLAGGED'], array_keys($messages['<3@google.com>']->flags));
        $this->assertSame(['SEEN'], array_keys($messages['<5@google.com>']->flags));
        $messages = $this->imapList($imap, 'Sent');
        $this->assertCount(1, $messages);
        $msg = array_shift($messages);
        $this->assertSame('<4@google.com>', $msg->messageID);

        // Assert the migrated events
        $events = $this->davList($dav, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(4, $events);
        $this->assertSame('ssss', $events['44gvk1rth37n1qk8nsml26o7og@google.com']->summary);

        $events = $this->davList($dav, 'Custom Calendar', Engine::TYPE_EVENT);
        $events = \collect($events)->keyBy('uid')->all();
        $this->assertCount(1, $events);
        $this->assertSame('TestY', $events['ps1tmklc3gvao@google.com']->summary);
    }

    /**
     * Initialize accounts for tests
     */
    private function getTestAccounts()
    {
        $dav_uri = \config('services.dav.uri');
        $dav_uri = preg_replace('|^http|', 'dav', $dav_uri);
        $imap_uri = \config('services.imap.uri');
        if (!str_contains($imap_uri, '://')) {
            $imap_uri = 'imap://' . $imap_uri;
        }

        $takeout_uri = 'takeout://' . self::BASE_DIR . '/data/takeout.zip?user=test@gmail.com';
        $kolab_uri = preg_replace('|^[a-z]+://|', 'kolab://jack%40kolab.org:simple123@', $imap_uri)
            . '?dav_host=' . preg_replace('|^davs?://|', '', $dav_uri);

        $imap = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $imap_uri));
        $dav = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $dav_uri));
        $src = new Account($takeout_uri);
        $dst = new Account($kolab_uri);

        return [$imap, $dav, $src, $dst];
    }
}
