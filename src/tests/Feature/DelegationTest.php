<?php

namespace Tests\Feature;

use App\Delegation;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test user deletion regarding delegation relations
     */
    public function testDeleteWithDelegation(): void
    {
        Queue::fake();

        // Test removing delegatee
        $userA = $this->getTestUser('UserAccountA@UserAccount.com');
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');

        $delegation = new Delegation();
        $delegation->user_id = $userA->id;
        $delegation->delegatee_id = $userB->id;
        $delegation->save();

        $delegation->delete();

        $this->assertNull(Delegation::find($delegation->id));

        Queue::assertPushed(\App\Jobs\User\Delegation\DeleteJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\User\Delegation\DeleteJob::class,
            function ($job) use ($userA, $userB) {
                $delegator = TestCase::getObjectProperty($job, 'delegatorEmail');
                $delegatee = TestCase::getObjectProperty($job, 'delegateeEmail');
                return $delegator === $userA->email && $delegatee === $userB->email;
            }
        );
    }
}
