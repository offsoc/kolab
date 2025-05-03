<?php

namespace Tests\Feature\Jobs\User\Delegation;

use App\Delegation;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\Roundcube;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('delegation-user1@' . \config('app.domain'));
        $this->deleteTestUser('delegation-user2@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
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

        $user = $this->getTestUser(
            'delegation-user1@' . \config('app.domain'),
            ['status' => User::STATUS_ACTIVE | User::STATUS_IMAP_READY]
        );
        $delegatee = $this->getTestUser(
            'delegation-user2@' . \config('app.domain'),
            ['status' => User::STATUS_ACTIVE | User::STATUS_IMAP_READY]
        );

        // Test successful creation
        IMAP::shouldReceive('unsubscribeSharedFolders')->once()->with($delegatee, $user->email)->andReturn(true);
        IMAP::shouldReceive('unshareFolders')->once()->with($user, $delegatee->email)->andReturn(true);
        DAV::shouldReceive('unsubscribeSharedFolders')->once()->with($delegatee, $user->email);
        DAV::shouldReceive('unshareFolders')->once()->with($user, $delegatee->email);
        Roundcube::shouldReceive('resetIdentities')->once()->with($delegatee);

        $job = (new \App\Jobs\User\Delegation\DeleteJob($user->email, $delegatee->email))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        // Test that we do nothing if delegation exists
        Delegation::create(['user_id' => $user->id, 'delegatee_id' => $delegatee->id]);

        $job = (new \App\Jobs\User\Delegation\DeleteJob($user->email, $delegatee->email))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();
    }
}
