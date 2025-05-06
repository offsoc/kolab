<?php

namespace Tests\Feature;

use App\Jobs\Mail\SignupInvitationJob;
use App\SignupInvitation as SI;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignupInvitationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SI::truncate();
    }

    protected function tearDown(): void
    {
        SI::truncate();

        parent::tearDown();
    }

    /**
     * Test SignupInvitation creation
     */
    public function testCreate(): void
    {
        Queue::fake();

        $invitation = SI::create(['email' => 'test@domain.org']);

        $this->assertSame('test@domain.org', $invitation->email);
        $this->assertSame(SI::STATUS_NEW, $invitation->status);
        $this->assertSame(\config('app.tenant_id'), $invitation->tenant_id);
        $this->assertTrue(preg_match('/^[a-f0-9-]{36}$/', $invitation->id) > 0);

        Queue::assertPushed(SignupInvitationJob::class, 1);

        Queue::assertPushed(
            SignupInvitationJob::class,
            static function ($job) use ($invitation) {
                $inv = TestCase::getObjectProperty($job, 'invitation');

                return $inv->id === $invitation->id && $inv->email === $invitation->email;
            }
        );

        $inst = SI::find($invitation->id);

        $this->assertInstanceOf(SI::class, $inst);
        $this->assertSame($inst->id, $invitation->id);
        $this->assertSame($inst->email, $invitation->email);
    }

    /**
     * Test SignupInvitation update
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $invitation = SI::create(['email' => 'test@domain.org']);

        Queue::fake();

        // Test that these status changes do not dispatch the email sending job
        foreach ([SI::STATUS_FAILED, SI::STATUS_SENT, SI::STATUS_COMPLETED, SI::STATUS_NEW] as $status) {
            $invitation->status = $status;
            $invitation->save();
        }

        Queue::assertNothingPushed();

        // SENT -> NEW should resend the invitation
        SI::where('id', $invitation->id)->update(['status' => SI::STATUS_SENT]);
        $invitation->refresh();
        $invitation->status = SI::STATUS_NEW;
        $invitation->save();

        Queue::assertPushed(SignupInvitationJob::class, 1);

        Queue::assertPushed(
            SignupInvitationJob::class,
            static function ($job) use ($invitation) {
                $inv = TestCase::getObjectProperty($job, 'invitation');

                return $inv->id === $invitation->id && $inv->email === $invitation->email;
            }
        );

        Queue::fake();

        // FAILED -> NEW should resend the invitation
        SI::where('id', $invitation->id)->update(['status' => SI::STATUS_FAILED]);
        $invitation->refresh();
        $invitation->status = SI::STATUS_NEW;
        $invitation->save();

        Queue::assertPushed(SignupInvitationJob::class, 1);

        Queue::assertPushed(
            SignupInvitationJob::class,
            static function ($job) use ($invitation) {
                $inv = TestCase::getObjectProperty($job, 'invitation');

                return $inv->id === $invitation->id && $inv->email === $invitation->email;
            }
        );
    }
}
