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
    private $code;

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $user = User::firstOrCreate([
                'email' => 'PasswordReset@UserAccount.com'
        ]);
        $this->code = new VerificationCode([
                'mode' => 'password-reset',
        ]);

        $user->verificationcodes()->save($this->code);
        $user->setSettings(['external_email' => 'etx@email.com']);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->code->user->delete();

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testPasswordResetEmailHandle()
    {
        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PasswordResetEmail($this->code);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PasswordReset::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(PasswordReset::class, function ($mail) {
            return $mail->hasTo($this->code->user->getSetting('external_email'));
        });
    }
}
