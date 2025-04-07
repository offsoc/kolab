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
        $job = (new \App\Jobs\Resource\DeleteJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource 123 could not be found in the database.");

        $resource = $this->getTestResource('resource-test@' . \config('app.domain'), [
                'status' => Resource::STATUS_NEW
        ]);

        // create the resource first
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $resource->refresh();

        $this->assertSame(\config('app.with_ldap'), $resource->isLdapReady());
        $this->assertTrue($resource->isImapReady());
        $this->assertFalse($resource->isDeleted());

        // Test deleting not deleted resource
        $job = (new \App\Jobs\Resource\DeleteJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource {$resource->id} is not deleted.");

        $resource->deleted_at = \now();
        $resource->saveQuietly();
        Queue::fake();

        // Test successful deletion
        $job = new \App\Jobs\Resource\DeleteJob($resource->id);
        $job->handle();

        $resource->refresh();

        $this->assertFalse($resource->isLdapReady());
        $this->assertFalse($resource->isImapReady());
        $this->assertTrue($resource->isDeleted());

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 0);

        // Test deleting already deleted resource
        $job = (new \App\Jobs\Resource\DeleteJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource {$resource->id} is already marked as deleted.");
    }
}
