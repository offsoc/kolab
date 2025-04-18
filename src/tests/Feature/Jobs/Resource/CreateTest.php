<?php

namespace Tests\Feature\Jobs\Resource;

use App\Resource;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
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
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test unknown resource
        $job = (new \App\Jobs\Resource\CreateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased();

        $resource = $this->getTestResource(
            'resource-test@' . \config('app.domain'),
            ['status' => Resource::STATUS_NEW]
        );

        $this->assertFalse($resource->isLdapReady());
        $this->assertFalse($resource->isImapReady());
        $this->assertFalse($resource->isActive());

        // TODO: Make the test working with various with_imap/with_ldap combinations
        \config(['app.with_imap' => true]);
        \config(['app.with_ldap' => true]);

        // Test resource creation
        IMAP::shouldReceive('createResource')->once()->with($resource)->andReturn(true);
        LDAP::shouldReceive('createResource')->once()->with($resource)->andReturn(true);

        $job = (new \App\Jobs\Resource\CreateJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $resource->refresh();

        $this->assertTrue($resource->isLdapReady());
        $this->assertTrue($resource->isImapReady());
        $this->assertTrue($resource->isActive());

        // TODO: Test case when IMAP or LDAP method fails

        // Test job failures
        $resource->status |= Resource::STATUS_DELETED;
        $resource->save();

        $job = (new \App\Jobs\Resource\CreateJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource {$resource->id} is marked as deleted.");

        $resource->status ^= Resource::STATUS_DELETED;
        $resource->save();
        $resource->delete();

        $job = (new \App\Jobs\Resource\CreateJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource {$resource->id} is actually deleted.");

        // TODO: Test failures on domain sanity checks
        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
