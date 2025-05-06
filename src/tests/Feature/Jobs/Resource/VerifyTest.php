<?php

namespace Tests\Feature\Jobs\Resource;

use App\Jobs\Resource\VerifyJob;
use App\Resource;
use App\Support\Facades\IMAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $resource = $this->getTestResource('resource-test1@kolab.org');
        $resource->status |= Resource::STATUS_IMAP_READY;
        $resource->save();
    }

    protected function tearDown(): void
    {
        $resource = $this->getTestResource('resource-test1@kolab.org');
        $resource->status |= Resource::STATUS_IMAP_READY;
        $resource->save();

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing resource ID
        $job = (new VerifyJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource 123 could not be found in the database.");

        $resource = $this->getTestResource('resource-test1@kolab.org');

        if ($resource->isImapReady()) {
            $resource->status ^= Resource::STATUS_IMAP_READY;
            $resource->save();
        }

        $this->assertFalse($resource->isImapReady());

        // Test existing resource (successful verification)
        IMAP::shouldReceive('verifySharedFolder')->once()->with($resource->getSetting('folder'))->andReturn(true);

        $job = new VerifyJob($resource->id);
        $job->handle();

        $resource->refresh();
        $this->assertTrue($resource->isImapReady());

        // Test existing resource (unsuccessful verification)
        $resource->status ^= Resource::STATUS_IMAP_READY;
        $resource->save();

        IMAP::shouldReceive('verifySharedFolder')->once()->with($resource->getSetting('folder'))->andReturn(false);

        $job = new VerifyJob($resource->id);
        $job->handle();

        $this->assertFalse($resource->fresh()->isImapReady());
    }
}
