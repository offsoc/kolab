<?php

namespace Tests\Feature\Jobs\SharedFolder;

use App\SharedFolder;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $folder = $this->getTestSharedFolder('folder-event@kolab.org');
        $folder->status |= SharedFolder::STATUS_IMAP_READY;
        $folder->save();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $folder = $this->getTestSharedFolder('folder-event@kolab.org');
        $folder->status |= SharedFolder::STATUS_IMAP_READY;
        $folder->save();

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing folder ID
        $job = new \App\Jobs\SharedFolder\VerifyJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Shared folder 123 could not be found in the database.", $job->failureMessage);

        // Test existing folder
        $folder = $this->getTestSharedFolder('folder-event@kolab.org');

        if ($folder->isImapReady()) {
            $folder->status ^= SharedFolder::STATUS_IMAP_READY;
            $folder->save();
        }

        $this->assertFalse($folder->isImapReady());

        for ($i = 0; $i < 10; $i++) {
            $job = new \App\Jobs\SharedFolder\VerifyJob($folder->id);
            $job->handle();

            if ($folder->fresh()->isImapReady()) {
                $this->assertTrue(true);
                return;
            }

            sleep(1);
        }

        $this->assertTrue(false, "Unable to verify the shared folder is set up in time");
    }
}
