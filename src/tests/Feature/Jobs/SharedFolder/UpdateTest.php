<?php

namespace Tests\Feature\Jobs\SharedFolder;

use App\Backends\LDAP;
use App\SharedFolder;
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

        $this->deleteTestSharedFolder('folder-test@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestSharedFolder('folder-test@' . \config('app.domain'));

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

        // Test non-existing folder ID
        $job = new \App\Jobs\SharedFolder\UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder 123 could not be found in the database.", $job->failureMessage);

        $folder = $this->getTestSharedFolder('folder-test@' . \config('app.domain'));

        // Create the folder in LDAP
        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $job = new \App\Jobs\SharedFolder\UpdateJob($folder->id);
        $job->handle();

        $this->assertTrue(is_array(LDAP::getSharedFolder($folder->email)));

        // Test that the job is being deleted if the folder is not ldap ready or is deleted
        $folder->refresh();
        $folder->status = SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE;
        $folder->save();

        $job = new \App\Jobs\SharedFolder\UpdateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->isDeleted());

        $folder->status = SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE
            | SharedFolder::STATUS_LDAP_READY | SharedFolder::STATUS_DELETED;
        $folder->save();

        $job = new \App\Jobs\SharedFolder\UpdateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->isDeleted());
    }
}
