<?php

namespace Tests\Feature\Jobs\Password;

use App\Jobs\Password\RetentionEmailJob;
use App\Mail\PasswordExpirationReminder;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RetentionEmailJobTest extends TestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('PasswordRetention@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('PasswordRetention@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testHandle()
    {
        $user = $this->getTestUser('PasswordRetention@UserAccount.com');
        $expiresOn = now()->copy()->addDays(7)->toDateString();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new RetentionEmailJob($user, $expiresOn);
        $job->handle();

        $this->assertMatchesRegularExpression(
            '/^' . now()->format('Y-m-d') . ' [0-9]{2}:[0-9]{2}:[0-9]{2}$/',
            $user->getSetting('password_expiration_warning')
        );

        // Assert the email sending job was pushed once
        Mail::assertSent(PasswordExpirationReminder::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(PasswordExpirationReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Assert sender
        Mail::assertSent(PasswordExpirationReminder::class, function ($mail) {
            return $mail->hasFrom(\config('mail.from.address'), \config('mail.from.name'))
                && $mail->hasReplyTo(\config('mail.reply_to.address'), \config('mail.reply_to.name'));
        });
    }
}
