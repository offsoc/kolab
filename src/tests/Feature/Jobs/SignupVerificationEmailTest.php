<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SignupVerificationEmail;
use App\Mail\SignupVerification;
use App\SignupCode;
use App\Tenant;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignupVerificationEmailTest extends TestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        SignupCode::where('email', 'SignupVerificationEmailTest1@' . \config('app.domain'))->delete();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        SignupCode::where('email', 'SignupVerificationEmailTest1@' . \config('app.domain'))->delete();

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testSignupVerificationEmailHandle()
    {
        $tenant = Tenant::orderBy('id', 'desc')->first();
        $tenant->setSetting('mail.sender.address', 'sender@tenant');
        $tenant->setSetting('mail.sender.name', 'Tenant');

        $code = new SignupCode();
        $code->email = 'SignupVerificationEmailTest1@' . \config('app.domain');
        $code->first_name = 'Test';
        $code->last_name = 'Job';
        $code->tenant_id = $tenant->id;
        $code->save();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new SignupVerificationEmail($code);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(SignupVerification::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(SignupVerification::class, function ($mail) use ($code) {
            return $mail->hasTo($code->email) && $mail->hasFrom('sender@tenant', 'Tenant');
        });
    }
}
