<?php

namespace Tests\Unit;

use App\SignupInvitation;
use Tests\TestCase;

class SignupInvitationTest extends TestCase
{
    /**
     * Test is*() methods
     */
    public function testStatus()
    {
        $invitation = new SignupInvitation();

        $statuses = [
            SignupInvitation::STATUS_NEW,
            SignupInvitation::STATUS_SENT,
            SignupInvitation::STATUS_FAILED,
            SignupInvitation::STATUS_COMPLETED,
        ];

        foreach ($statuses as $status) {
            $invitation->status = $status;

            $this->assertSame($status === SignupInvitation::STATUS_NEW, $invitation->isNew());
            $this->assertSame($status === SignupInvitation::STATUS_SENT, $invitation->isSent());
            $this->assertSame($status === SignupInvitation::STATUS_FAILED, $invitation->isFailed());
            $this->assertSame($status === SignupInvitation::STATUS_COMPLETED, $invitation->isCompleted());
        }
    }
}
