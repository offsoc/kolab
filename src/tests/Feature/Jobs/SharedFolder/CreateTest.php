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
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test unknown folder
        $job = (new \App\Jobs\SharedFolder\CreateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased(delay: 5);

        $folder = $this->getTestSharedFolder(
            'folder-test@' . \config('app.domain'),
            ['status' => SharedFolder::STATUS_NEW]
        );

        $this->assertFalse($folder->isLdapReady());
        $this->assertFalse($folder->isImapReady());
        $this->assertFalse($folder->isActive());

        // Test shared folder creation
        $job = (new \App\Jobs\SharedFolder\CreateJob($folder->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $folder->refresh();

        if (\config('app.with_ldap')) {
            $this->assertTrue($folder->isLdapReady());
        } else {
            $this->assertFalse($folder->isLdapReady());
        }
        if (\config('app.with_imap')) {
            $this->assertTrue($folder->isImapReady());
        } else {
            $this->assertFalse($folder->isImapReady());
        }
        $this->assertTrue($folder->isActive());

        // Test job failures
        $folder->status |= SharedFolder::STATUS_DELETED;
        $folder->save();

        $job = (new \App\Jobs\SharedFolder\CreateJob($folder->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Shared folder {$folder->id} is marked as deleted.");

        $folder->status ^= SharedFolder::STATUS_DELETED;
        $folder->save();
        $folder->delete();

        $job = (new \App\Jobs\SharedFolder\CreateJob($folder->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Shared folder {$folder->id} is actually deleted.");

        // TODO: Test failures on domain sanity checks
        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
