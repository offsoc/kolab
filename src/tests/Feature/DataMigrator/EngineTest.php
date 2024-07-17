<?php

namespace Tests\Feature\DataMigrator;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Queue as MigratorQueue;
use Tests\TestCase;

class EngineTest extends TestCase
{
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
        $this->markTestIncomplete();
    }

    /**
     * Test synchronous migration
     */
    public function testSyncMigration(): void
    {
        $this->markTestIncomplete();
    }
}
