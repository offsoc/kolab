<?php

namespace Tests\Feature\Jobs\Resource;

use App\Backends\LDAP;
use App\Resource;
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
     *
     * @group ldap
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing resource ID
        $job = new \App\Jobs\Resource\UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Resource 123 could not be found in the database.", $job->failureMessage);

        $resource = $this->getTestResource(
            'resource-test@' . \config('app.domain'),
            ['status' => Resource::STATUS_NEW]
        );

        // Create the resource in LDAP
        $job = new \App\Jobs\Resource\CreateJob($resource->id);
        $job->handle();

        // Run the update with some new config
        $resource->setConfig(['invitation_policy' => 'accept']);

        $job = new \App\Jobs\Resource\UpdateJob($resource->id);
        $job->handle();

        $ldap_resource = LDAP::getResource($resource->email);

        $this->assertSame('ACT_ACCEPT', $ldap_resource['kolabinvitationpolicy']);

        // TODO: Assert IMAP change worked

        // Test that the job is being deleted if the resource is not ldap ready or is deleted
        $resource->refresh();
        $resource->status |= Resource::STATUS_DELETED;
        $resource->save();

        $job = new \App\Jobs\Resource\UpdateJob($resource->id);
        $job->handle();

        $this->assertTrue($job->isDeleted());
    }
}
