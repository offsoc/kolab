<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UserCreate;
use App\User;
use Illuminate\Support\Facades\Mail;
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
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        $this->assertFalse($user->isLdapReady());

        /*
        $mock = \Mockery::mock('alias:App\Backends\LDAP');
        $mock->shouldReceive('createUser')
            ->once()
            ->with($user)
            ->andReturn(null);
        */

        $job = new UserCreate($user);
        $job->handle();

        $this->assertTrue($user->fresh()->isLdapReady());
    }
}
