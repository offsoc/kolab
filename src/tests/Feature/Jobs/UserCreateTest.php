<?php

namespace Tests\Feature\Jobs;

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
    }
}
