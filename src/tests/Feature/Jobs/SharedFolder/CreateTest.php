<?php

namespace Tests\Feature\Jobs\SharedFolder;

use App\SharedFolder;
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

        // Test unknown folder
        $job = new \App\Jobs\SharedFolder\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());

        $folder = $this->getTestSharedFolder('folder-test@' . \config('app.domain'));

        $this->assertFalse($folder->isLdapReady());

        // Test shared folder creation
        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $this->assertTrue($folder->fresh()->isLdapReady());
        $this->assertFalse($job->hasFailed());

        // Test job failures
        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder {$folder->id} is already marked as ldap-ready.", $job->failureMessage);

        $folder->status |= SharedFolder::STATUS_DELETED;
        $folder->save();

        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder {$folder->id} is marked as deleted.", $job->failureMessage);

        $folder->status ^= SharedFolder::STATUS_DELETED;
        $folder->save();
        $folder->delete();

        $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder {$folder->id} is actually deleted.", $job->failureMessage);

        // TODO: Test failures on domain sanity checks
    }
}
