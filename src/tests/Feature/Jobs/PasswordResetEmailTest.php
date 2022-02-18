<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PasswordResetEmail;
use App\Mail\PasswordReset;
use App\User;
use App\VerificationCode;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('PasswordReset@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('PasswordReset@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testPasswordResetEmailHandle()
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

        $job = new PasswordResetEmail($code);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PasswordReset::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(PasswordReset::class, function ($mail) use ($code) {
            return $mail->hasTo($code->user->getSetting('external_email'));
        });

        // Assert sender
        Mail::assertSent(PasswordReset::class, function ($mail) {
            return $mail->hasFrom(\config('mail.from.address'), \config('mail.from.name'))
                && $mail->hasReplyTo(\config('mail.reply_to.address'), \config('mail.reply_to.name'));
        });
    }
}
