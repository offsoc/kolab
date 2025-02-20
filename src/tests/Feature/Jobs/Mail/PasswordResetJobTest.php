<?php

namespace Tests\Feature\Jobs\Mail;

use App\Jobs\Mail\PasswordResetJob;
use App\Mail\PasswordReset;
use App\User;
use App\VerificationCode;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetJobTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('PasswordReset@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('PasswordReset@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $code = new VerificationCode([
                'mode' => 'password-reset',
        ]);

        $user = $this->getTestUser('PasswordReset@UserAccount.com');
        $user->verificationcodes()->save($code);
        $user->setSettings(['external_email' => 'etx@email.com']);

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PasswordResetJob($code);
        $job->handle();

        $this->assertSame(PasswordResetJob::QUEUE, $job->queue);

        // Assert the email sending job was pushed once
        Mail::assertSent(PasswordReset::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(PasswordReset::class, function ($mail) use ($code) {
            return $mail->hasTo($code->user->getSetting('external_email'));
        });

        // Assert sender
        Mail::assertSent(PasswordReset::class, function ($mail) {
            return $mail->hasFrom(\config('mail.sender.address'), \config('mail.sender.name'))
                && $mail->hasReplyTo(\config('mail.replyto.address'), \config('mail.replyto.name'));
        });
    }
}
