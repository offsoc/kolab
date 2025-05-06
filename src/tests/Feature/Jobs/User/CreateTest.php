<?php

namespace Tests\Feature\Jobs\User;

use App\Domain;
use App\Jobs\User\CreateJob;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('new-job-user@' . \config('app.domain'));
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('new-job-user@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('new-job-user@' . \config('app.domain'), ['status' => User::STATUS_NEW]);
        $domain = Domain::where('namespace', \config('app.domain'))->first();
        $domain->status |= Domain::STATUS_LDAP_READY;
        $domain->save();

        // TODO: Make the test working with various with_imap/with_ldap combinations
        \config(['app.with_ldap' => true]);
        \config(['app.with_imap' => true]);

        $this->assertFalse($user->isLdapReady());
        $this->assertFalse($user->isImapReady());
        $this->assertFalse($user->isActive());

        // Test successful creation
        DAV::shouldReceive('initDefaultFolders')->once()->with($user);
        IMAP::shouldReceive('createUser')->once()->with($user)->andReturn(true);
        LDAP::shouldReceive('createUser')->once()->with($user)->andReturn(true);

        $job = (new CreateJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $user->refresh();

        $this->assertTrue($user->isLdapReady());
        $this->assertTrue($user->isImapReady());
        $this->assertTrue($user->isActive());

        // Test job failure (user deleted)
        $user->status |= User::STATUS_DELETED;
        $user->save();

        $job = (new CreateJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("User {$user->id} is marked as deleted.");

        // Test job failure (user removed)
        $user->status ^= User::STATUS_DELETED;
        $user->save();
        $user->delete();

        $job = (new CreateJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("User {$user->id} is actually deleted.");

        // Test job failure (user unknown), the job will be released
        $job = (new CreateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased(delay: 5);

        // TODO: Test failures on domain sanity checks
        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
