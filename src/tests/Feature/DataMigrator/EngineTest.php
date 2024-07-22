<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Jobs\FolderJob;
use App\DataMigrator\Jobs\ItemJob;
use App\DataMigrator\Queue as MigratorQueue;
use App\DataMigrator\Test;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EngineTest extends TestCase
{
    protected $data = [
        'Inbox' => [
            'type' => Engine::TYPE_MAIL,
            'name' => 'Inbox',
            'fullname' => 'Inbox',
            'items' => [
                'm1' => [],
                'm2' => [],
                'm3' => [],
                'm4' => [],
            ],
            'existing_items' => [
            ],
        ],
        'Contacts' => [
            'type' => Engine::TYPE_CONTACT,
            'name' => 'Contacts',
            'fullname' => 'Contacts',
            'items' => [
                'c1' => [],
                'c2' => [],
            ],
            'existing_items' => [
            ],
        ],
        'Calendar' => [
            'type' => Engine::TYPE_EVENT,
            'name' => 'Calendar',
            'fullname' => 'Calendar',
            'items' => [
                'e1' => [],
                'e2' => [],
            ],
            'existing_items' => [
            ],
        ],
    ];

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
     * Test asynchronous migration
     */
    public function testAsyncMigration(): void
    {
        $source = new Account('test://test%40domain.tld:test@test');
        $destination = new Account('test://test%40kolab.org:test@test');
        $engine = new Engine();

        Test::init($this->data);
        Queue::fake();

        // Migration initial run
        $engine->migrate($source, $destination, []);

        $queue = MigratorQueue::first();

        Queue::assertPushed(FolderJob::class, 3);
        Queue::assertPushed(
            FolderJob::class,
            function ($job) use ($queue) {
                $folder = TestCase::getObjectProperty($job, 'folder');
                return $folder->id === 'Inbox' && $folder->queueId = $queue->id;
            }
        );
        Queue::assertPushed(
            FolderJob::class,
            function ($job) use ($queue) {
                $folder = TestCase::getObjectProperty($job, 'folder');
                return $folder->id === 'Contacts' && $folder->queueId = $queue->id;
            }
        );
        Queue::assertPushed(
            FolderJob::class,
            function ($job) use ($queue) {
                $folder = TestCase::getObjectProperty($job, 'folder');
                return $folder->id === 'Calendar' && $folder->queueId = $queue->id;
            }
        );

        $this->assertCount(0, Test::$createdFolders);
        $this->assertSame(3, $queue->jobs_started);
        $this->assertSame(0, $queue->jobs_finished);
        $this->assertSame([], $queue->data['options']);
        // TODO: Assert Source and destination in the queue
        // TODO: Test 'force' option, test executing with an existing queue
        // TODO: Test jobs execution
        $this->markTestIncomplete();
    }

    /**
     * Test synchronous migration
     */
    public function testSyncMigration(): void
    {
        $source = new Account('test://test%40domain.tld:test@test');
        $destination = new Account('test://test%40kolab.org:test@test');
        $engine = new Engine();

        Test::init($this->data);
        Queue::fake();

        $engine->migrate($source, $destination, ['sync' => true]);

        $queue = MigratorQueue::first();

        Queue::assertNothingPushed();

        $this->assertSame(0, $queue->jobs_started);
        $this->assertSame(0, $queue->jobs_finished);
        $this->assertSame(['sync' => true], $queue->data['options']);

        $this->assertCount(3, Test::$createdFolders);
        $this->assertCount(8, Test::$createdItems);
        $this->assertCount(8, Test::$fetchedItems);

        Test::init($this->data);

        // Test 'type' argument
        $engine->migrate($source, $destination, ['sync' => true, 'type' => 'contact,event']);

        $queue = MigratorQueue::whereNot('id', $queue->id)->first();

        Queue::assertNothingPushed();

        $this->assertSame(0, $queue->jobs_started);
        $this->assertSame(0, $queue->jobs_finished);
        $this->assertSame(['sync' => true, 'type' => 'contact,event'], $queue->data['options']);

        $this->assertCount(2, Test::$createdFolders);
        $this->assertCount(4, Test::$createdItems);
        $this->assertCount(4, Test::$fetchedItems);
    }
}
