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
     * @group imap
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

        $resource = $this->getTestResource(
            'resource-test@' . \config('app.domain'),
            ['status' => Resource::STATUS_NEW]
        );

        $this->assertFalse($resource->isLdapReady());
        $this->assertFalse($resource->isImapReady());
        $this->assertFalse($resource->isActive());

        // Test resource creation
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        $resource->refresh();

        $this->assertFalse($job->hasFailed());
        $this->assertSame(\config('app.with_ldap'), $resource->isLdapReady());
        $this->assertTrue($resource->isImapReady());
        $this->assertTrue($resource->isActive());

        // Test job failures
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
        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
