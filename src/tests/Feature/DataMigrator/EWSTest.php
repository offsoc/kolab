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
class EWSTest extends TestCase
{
    use BackendsTrait;

    private const EVENT1 = '1F3C13D7E99642A75ABE23D50487B454-8FE68B2E68E1B348';
    private const EVENT2 = '040000008200E00074C5B7101A82E00800000000B6140C77B81BDA01000000000000000'
                         . '010000000FA0F989A1B3C20499E1DE5B68DB1E339';

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

        @unlink(__DIR__ . '/../../data/ews/initial/saveState.json');
        @unlink(__DIR__ . '/../../data/ews/incremental/saveState.json');

        parent::tearDown();
    }

    /**
     * Test EWS to DAV migration
     *
     * @group ews dav
     */
    public function testInitialMigration(): void
    {
        $uri = \config('services.dav.uri');

        $src = new Account('ews://user%40outlook.com:pass@office.outlook.com');
        $dst = new Account(preg_replace('|^[a-z]+://|', 'dav://jack%40kolab.org:simple123@', $uri));

        // Cleanup the DAV account
        $this->davEmptyFolder($dst, 'Calendar', Engine::TYPE_EVENT);
        $this->davEmptyFolder($dst, 'Tasks', Engine::TYPE_TASK);
        $this->davDeleteFolder($dst, 'Kontakty', Engine::TYPE_CONTACT);

        $options = [
            'force' => true,
            'sync' => true,
            'type' => 'event,contact,task',
            // Mocking, use HTTP responses from the playback file
            'httpPlayback' => [
                'mode' => 'playback',
                'recordLocation' => $this->buildPlaybackFile('initial'),
            ],
        ];

        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, $options);

        // Assert the migrated events
        $dstObjects = $this->davList($dst, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($dstObjects)->keyBy('uid')->all();
        $this->assertCount(2, $events);
        $this->assertSame('test to ms', $events[self::EVENT1]->summary);
        $this->assertSame('test ms3', $events[self::EVENT2]->summary);

        // Assert the migrated tasks
        // Note: Tasks do not have UID in Exchange so it's generated
        $tasks = $this->davList($dst, 'Tasks', Engine::TYPE_TASK);
        $this->assertCount(1, $tasks);
        $this->assertSame('Nowe zadanie', $tasks[0]->summary);

        // Assert the migrated contacts and contact folders
        // Note: Contacts do not have UID in Exchange so it's generated
        $dstObjects = $this->davList($dst, 'Kontakty', Engine::TYPE_CONTACT);
        $contacts = \collect($dstObjects)->keyBy('fn')->all();

        $this->assertCount(3, $contacts);
        $this->assertSame(null, $contacts['Nowy Kontakt']->kind);
        $this->assertSame(null, $contacts['Test Surname']->kind);
        $this->assertSame('group', $contacts['nowa lista']->kind);
    }

    /**
     * Test EWS to DAV incremental migration run
     *
     * @group ews dav
     * @depends testInitialMigration
     */
    public function testIncrementalMigration(): void
    {
        $uri = \config('services.dav.uri');

        // TODO: Test OAuth2 authentication

        $src = new Account('ews://user%40outlook.com:pass@office.outlook.com');
        $dst = new Account(preg_replace('|^[a-z]+://|', 'dav://jack%40kolab.org:simple123@', $uri));

        $options = [
            'force' => true,
            'sync' => true,
            'type' => 'event,contact',
            // Mocking, use HTTP responses from the playback file
            'httpPlayback' => [
                'mode' => 'playback',
                'recordLocation' => $this->buildPlaybackFile('incremental'),
            ],
        ];

        // We added a new contact and modified another one
        // Run the migration
        $migrator = new Engine();
        $migrator->migrate($src, $dst, $options);

        $dstObjects = $this->davList($dst, 'Calendar', Engine::TYPE_EVENT);
        $events = \collect($dstObjects)->keyBy('uid')->all();
        $this->assertCount(2, $events);
        $this->assertSame('test to ms', $events[self::EVENT1]->summary);
        $this->assertSame('test ms3', $events[self::EVENT2]->summary);

        // Assert the migrated contacts
        // Note: Contacts do not have UID in Exchange so it's generated
        $dstObjects = $this->davList($dst, 'Kontakty', Engine::TYPE_CONTACT);
        $contacts = \collect($dstObjects)->keyBy('fn')->all();

        $this->assertCount(4, $contacts);
        $this->assertSame(null, $contacts['Nowy Kontakt']->kind);
        $this->assertSame(null, $contacts['Test Surname 1']->kind);
        $this->assertSame(null, $contacts['Test New']->kind);
        $this->assertSame('group', $contacts['nowa lista']->kind);

        // TODO: Assert that unmodified objects aren't migrated again,
        // although our httpPlayback makes that it would fail otherwise.

        // TODO: Test migrating a task/event with attachments
    }

    /**
     * Test OAuth2 use
     *
     * @group ews dav
     * @depends testIncrementalMigration
     */
    public function testOAuth2(): void
    {
        // TODO: Test OAuth2 authentication with HTTP client fake

        $this->markTestIncomplete();
    }

    /**
     * Build a playback file for EWS client to mock communication with Exchange.
     */
    private function buildPlaybackFile($type)
    {
        /* The .json and .xml files were produced with the follwoing script
           having saveState.json produced by httpPlayback in 'record' mode.
           Separated and formatted for better understanding and modification ability.

            $file = file_get_contents('saveState.json');

            $data = json_decode($file, true);

            foreach ($data as $idx => $record) {
                file_put_contents("a{$idx}.xml", $record['body']);
                unset($record['body']);
                file_put_contents("$idx.json", json_encode($record, JSON_PRETTY_PRINT));
                exec("xmllint --format a{$idx}.xml >> $idx.xml");
                unlink("a{$idx}.xml");
            }
        */

        $dir = __DIR__ . '/../../data/ews/' . $type;
        $playback = [];

        foreach (glob("{$dir}/[0-9].json") as $file) {
            $id = basename($file, '.json');

            $response = json_decode(file_get_contents($file), true);
            $response['body'] = file_get_contents(str_replace('.json', '.xml', $file));

            $playback[(int) $id] = $response;
        }

        ksort($playback);

        file_put_contents($dir . '/' . 'saveState.json', json_encode($playback));

        return $dir;
    }
}
