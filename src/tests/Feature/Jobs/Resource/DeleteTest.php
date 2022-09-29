<?php

namespace Tests\Feature\Jobs\Resource;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestResource('resource-test@' . \config('app.domain'));
    }

    public function tearDown(): void
    {
        $this->deleteTestResource('resource-test@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group ldap
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing resource ID
        $job = new \App\Jobs\Resource\DeleteJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource 123 could not be found in the database.", $job->failureMessage);

        $resource = $this->getTestResource('resource-test@' . \config('app.domain'), [
                'status' => Resource::STATUS_NEW
        ]);

        // create the resource first
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $resource->refresh();

        $this->assertTrue($resource->isLdapReady());
        $this->assertTrue($resource->isImapReady());
        $this->assertFalse($resource->isDeleted());

        // Test successful deletion
        $job = new \App\Jobs\Resource\DeleteJob($resource->id);
        $job->handle();

        $resource->refresh();

        $this->assertFalse($resource->isLdapReady());
        $this->assertFalse($resource->isImapReady());
        $this->assertTrue($resource->isDeleted());

        // Test deleting already deleted resource
        $job = new \App\Jobs\Resource\DeleteJob($resource->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource {$resource->id} is already marked as deleted.", $job->failureMessage);
    }
}
