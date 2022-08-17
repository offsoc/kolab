<?php

namespace Tests\Feature\Jobs\Resource;

use App\Resource;
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
     *
     * @group ldap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test unknown resource
        $this->expectException(\Exception::class);
        $job = new \App\Jobs\Resource\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());

        $resource = $this->getTestResource('resource-test@' . \config('app.domain'));

        $this->assertFalse($resource->isLdapReady());

        // Test resource creation
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $this->assertTrue($resource->fresh()->isLdapReady());
        $this->assertFalse($job->hasFailed());

        // Test job failures
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource {$resource->id} is already marked as ldap-ready.", $job->failureMessage);

        $resource->status |= Resource::STATUS_DELETED;
        $resource->save();

        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource {$resource->id} is marked as deleted.", $job->failureMessage);

        $resource->status ^= Resource::STATUS_DELETED;
        $resource->save();
        $resource->delete();

        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource {$resource->id} is actually deleted.", $job->failureMessage);

        // TODO: Test failures on domain sanity checks
    }
}
