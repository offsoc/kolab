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
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing folder ID
        $job = new \App\Jobs\SharedFolder\UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder 123 could not be found in the database.", $job->failureMessage);

        $folder = $this->getTestSharedFolder(
            'folder-test@' . \config('app.domain'),
            ['status' => SharedFolder::STATUS_NEW]
        );

        // Create the folder in LDAP
        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $folder->refresh();

        $this->assertTrue($folder->isLdapReady());
        $this->assertTrue($folder->isImapReady());

        // Run the update job
        $job = new \App\Jobs\SharedFolder\UpdateJob($folder->id);
        $job->handle();

        // TODO: Assert that it worked on both LDAP and IMAP side

        // Test handling deleted folder
        $folder->status |= SharedFolder::STATUS_DELETED;
        $folder->save();

        $job = new \App\Jobs\SharedFolder\UpdateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->isDeleted());
    }
}
