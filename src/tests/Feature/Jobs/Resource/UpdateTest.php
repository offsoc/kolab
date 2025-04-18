<?php

namespace Tests\Feature\Jobs\Resource;

use App\Resource;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestResource('resource-test@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
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

        // Test non-existing resource ID
        $job = (new \App\Jobs\Resource\UpdateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Resource 123 could not be found in the database.");

        $resource = $this->getTestResource(
            'resource-test@' . \config('app.domain'),
            ['status' => Resource::STATUS_NEW | Resource::STATUS_LDAP_READY | Resource::STATUS_IMAP_READY]
        );

        // Run the update with some new config
        $resource->setConfig(['invitation_policy' => 'accept']);

        // TODO: Make the test working with various with_imap/with_ldap combinations
        \config(['app.with_imap' => true]);
        \config(['app.with_ldap' => true]);

        IMAP::shouldReceive('updateResource')->once()->with($resource, [])->andReturn(true);
        LDAP::shouldReceive('updateResource')->once()->with($resource)->andReturn(true);

        $job = new \App\Jobs\Resource\UpdateJob($resource->id);
        $job->handle();

        // Test that the job is being deleted if the resource is not ldap ready or is deleted
        $resource->refresh();
        $resource->status |= Resource::STATUS_DELETED;
        $resource->save();

        $job = (new \App\Jobs\Resource\UpdateJob($resource->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertDeleted();
    }
}
