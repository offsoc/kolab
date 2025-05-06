<?php

namespace Tests\Feature\Jobs\User\Delegation;

use App\Support\Facades\Roundcube;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('delegation-user1@' . \config('app.domain'));
        $this->deleteTestUser('delegation-user2@' . \config('app.domain'));
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('delegation-user1@' . \config('app.domain'));
        $this->deleteTestUser('delegation-user2@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('delegation-user1@' . \config('app.domain'));

        // Test successful creation
        Roundcube::shouldReceive('resetIdentities')->once()->with($user);

        $job = (new \App\Jobs\User\Delegation\UserRefreshJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();
    }
}
