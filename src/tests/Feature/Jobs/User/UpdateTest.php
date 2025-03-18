<?php

namespace Tests\Feature\Jobs\User;

use App\Backends\LDAP;
use App\Jobs\User\UpdateJob;
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

        $this->deleteTestUser('new-job-user@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
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
        // Ignore any jobs created here (e.g. on setAliases() use)
        Queue::fake();

        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        try {
            $job = new \App\Jobs\User\CreateJob($user->id);
            $job->handle();
        } catch (\Exception $e) {
            // Ignore "Attempted to release a manually executed job" exception
        }

        // Test setting two aliases
        $aliases = [
            'new-job-user1@' . \config('app.domain'),
            'new-job-user2@' . \config('app.domain'),
        ];

        $user->setAliases($aliases);

        $job = new UpdateJob($user->id);
        $job->handle();

        if (\config('app.with_ldap')) {
            $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

            $this->assertSame($aliases, $ldap_user['alias']);
        }

        // Test updating aliases list
        $aliases = [
            'new-job-user1@' . \config('app.domain'),
        ];

        $user->setAliases($aliases);

        $job = new UpdateJob($user->id);
        $job->handle();

        if (\config('app.with_ldap')) {
            $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

            $this->assertSame($aliases, (array) $ldap_user['alias']);
        }

        // Test unsetting aliases list
        $aliases = [];
        $user->setAliases($aliases);

        $job = new UpdateJob($user->id);
        $job->handle();

        if (\config('app.with_ldap')) {
            $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

            $this->assertTrue(empty($ldap_user['alias']));
        }

        // Test deleted user
        $user->delete();
        $job = new UpdateJob($user->id);
        $job->handle();

        $this->assertTrue($job->isDeleted());

        // Test job failure (user unknown)
        // The job will be released
        $this->expectException(\Exception::class);
        $job = new UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());

        // TODO: Test IMAP, e.g. quota change
    }
}
