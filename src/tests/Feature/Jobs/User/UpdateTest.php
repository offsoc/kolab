<?php

namespace Tests\Feature\Jobs\User;

use App\Backends\LDAP;
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

        $job = new \App\Jobs\User\UpdateJob($user->id);
        $job->handle();

        $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

        $this->assertSame($aliases, $ldap_user['alias']);

        // Test updating aliases list
        $aliases = [
            'new-job-user1@' . \config('app.domain'),
        ];

        $user->setAliases($aliases);

        $job = new \App\Jobs\User\UpdateJob($user->id);
        $job->handle();

        $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

        $this->assertSame($aliases, (array) $ldap_user['alias']);

        // Test unsetting aliases list
        $aliases = [];
        $user->setAliases($aliases);

        $job = new \App\Jobs\User\UpdateJob($user->id);
        $job->handle();

        $ldap_user = LDAP::getUser('new-job-user@' . \config('app.domain'));

        $this->assertTrue(empty($ldap_user['alias']));

        // Test non-existing user ID
        $job = new \App\Jobs\User\UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User 123 could not be found in the database.", $job->failureMessage);

        // TODO: Test IMAP, e.g. quota change
    }
}
