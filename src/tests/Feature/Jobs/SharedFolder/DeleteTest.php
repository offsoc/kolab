<?php

namespace Tests\Feature\Jobs\SharedFolder;

use App\SharedFolder;
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

        $this->deleteTestSharedFolder('folder-test@' . \config('app.domain'));
    }

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
        $job = new \App\Jobs\SharedFolder\DeleteJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder 123 could not be found in the database.", $job->failureMessage);

        $folder = $this->getTestSharedFolder('folder-test@' . \config('app.domain'), [
                'status' => SharedFolder::STATUS_NEW
        ]);

        // create the shared folder first
        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $folder->refresh();

        $this->assertTrue($folder->isLdapReady());

        // Test successful deletion
        $folder->status |= SharedFolder::STATUS_IMAP_READY;
        $folder->save();

        $job = new \App\Jobs\SharedFolder\DeleteJob($folder->id);
        $job->handle();

        $folder->refresh();

        $this->assertFalse($folder->isLdapReady());
        $this->assertFalse($folder->isImapReady());
        $this->assertTrue($folder->isDeleted());

        // Test deleting already deleted folder
        $job = new \App\Jobs\SharedFolder\DeleteJob($folder->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder {$folder->id} is already marked as deleted.", $job->failureMessage);
    }
}
