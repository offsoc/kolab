<?php

namespace Tests\Feature\Jobs\Resource;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $resource = $this->getTestResource('resource-test1@kolab.org');
        $resource->status |= Resource::STATUS_IMAP_READY;
        $resource->save();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $resource = $this->getTestResource('resource-test1@kolab.org');
        $resource->status |= Resource::STATUS_IMAP_READY;
        $resource->save();

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing resource ID
        $job = (new \App\Jobs\Resource\VerifyJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource 123 could not be found in the database.");

        // Test existing resource
        $resource = $this->getTestResource('resource-test1@kolab.org');

        if ($resource->isImapReady()) {
            $resource->status ^= Resource::STATUS_IMAP_READY;
            $resource->save();
        }

        $this->assertFalse($resource->isImapReady());

        for ($i = 0; $i < 10; $i++) {
            $job = new \App\Jobs\Resource\VerifyJob($resource->id);
            $job->handle();

            if ($resource->fresh()->isImapReady()) {
                $this->assertTrue(true);
                return;
            }

            sleep(1);
        }

        $this->assertTrue(false, "Unable to verify the shared folder is set up in time");
    }
}
