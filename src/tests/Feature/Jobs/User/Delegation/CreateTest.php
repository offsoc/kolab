<?php

namespace Tests\Feature\Jobs\User\Delegation;

use App\Delegation;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\Roundcube;
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

        $delegation = Delegation::create(['user_id' => $user->id, 'delegatee_id' => $delegatee->id]);

        $this->assertFalse($delegation->isActive());

        // Test successful creation
        IMAP::shouldReceive('shareDefaultFolders')->once()->with($user, $delegatee, $delegation->options)
            ->andReturn(true);
        DAV::shouldReceive('shareDefaultFolders')->once()->with($user, $delegatee, $delegation->options);
        Roundcube::shouldReceive('createDelegatedIdentities')->once()->with($delegatee, $user);

        $job = (new \App\Jobs\User\Delegation\CreateJob($delegation->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $this->assertTrue($delegation->fresh()->isActive());

        // TODO: Test all failure cases
        $this->markTestIncomplete();
    }
}
