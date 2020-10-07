<?php

namespace Tests\Feature\Jobs;

use App\Backends\LDAP;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserUpdateTest extends TestCase
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
     */
    public function testHandle(): void
    {
        // Ignore any jobs created here (e.g. on setAliases() use)
        Queue::fake();

        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        // Create the user in LDAP
        $job = new \App\Jobs\User\CreateJob($user->id);
        $job->handle();

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
    }
}
