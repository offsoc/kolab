<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SignupVerificationEmail;
use App\Mail\SignupVerification;
use App\SignupCode;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignupVerificationEmailTest extends TestCase
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

        $this->code = SignupCode::create([
                'email' => 'SignupVerificationEmailTest1@' . \config('app.domain'),
                'first_name' => "Test",
                'last_name' => "Job"
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->code->delete();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testSignupVerificationEmailHandle()
    {
        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new SignupVerificationEmail($this->code);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(SignupVerification::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(SignupVerification::class, function ($mail) {
            return $mail->hasTo($this->code->email);
        });
    }
}
