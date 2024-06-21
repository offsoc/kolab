<?php

namespace Tests\Feature\Jobs\User;

use App\User;
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

        $this->deleteTestUser('new-job-user@' . \config('app.domain'));
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('new-job-user@' . \config('app.domain'));

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
        $user = $this->getTestUser('new-job-user@' . \config('app.domain'), ['status' => User::STATUS_NEW]);
        $domain = \App\Domain::where('namespace', \config('app.domain'))->first();
        $domain->status |= \App\Domain::STATUS_LDAP_READY;
        $domain->save();

        // TODO: Make the test working with various with_imap/with_ldap combinations
        \config(['app.with_imap' => true]);
        \config(['app.with_ldap' => true]);

        $this->assertFalse($user->isLdapReady());
        $this->assertFalse($user->isImapReady());
        $this->assertFalse($user->isActive());

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $user->refresh();

        $this->assertTrue($user->isLdapReady());
        $this->assertTrue($user->isImapReady());
        $this->assertTrue($user->isActive());
        $this->assertFalse($job->hasFailed());

        // Test job failure (user deleted)
        $user->status |= User::STATUS_DELETED;
        $user->save();

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is marked as deleted.", $job->failureMessage);

        // Test job failure (user removed)
        $user->status ^= User::STATUS_DELETED;
        $user->save();
        $user->delete();

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is actually deleted.", $job->failureMessage);

        // Test job failure (user unknown)
        // The job will be released
        $this->expectException(\Exception::class);
        $job = new \App\Jobs\User\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());

        // TODO: Test failures on domain sanity checks
        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
