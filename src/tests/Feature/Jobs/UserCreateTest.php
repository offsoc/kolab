<?php

namespace Tests\Feature\Jobs;

use App\User;
use Tests\TestCase;

class UserCreateTest extends TestCase
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
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        $this->assertFalse($user->isLdapReady());

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($user->fresh()->isLdapReady());
        $this->assertFalse($job->hasFailed());

        // Test job failures
        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is already marked as ldap-ready.", $job->failureMessage);

        $user->status |= User::STATUS_DELETED;
        $user->save();

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is marked as deleted.", $job->failureMessage);

        $user->status ^= User::STATUS_DELETED;
        $user->save();
        $user->delete();

        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is actually deleted.", $job->failureMessage);

        // TODO: Test failures on domain sanity checks

        $job = new \App\Jobs\User\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());
    }
}
