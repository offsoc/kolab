<?php

namespace Tests\Feature\Controller;

use App\VerificationCode;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('passwordresettest@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('passwordresettest@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test password-reset/init with invalid input
     */
    public function testPasswordResetInitInvalidInput(): void
    {
        // Empty input data
        $data = [];

        $response = $this->post('/api/auth/password-reset/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);

        // Data with invalid email
        $data = [
            'email' => '@example.org',
        ];

        $response = $this->post('/api/auth/password-reset/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);

        // Data with valid but non-existing email
        $data = [
            'email' => 'non-existing-password-reset@example.org',
        ];

        $response = $this->post('/api/auth/password-reset/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);

        // Data with valid email af an existing user with no external email
        $data = [
            'email' => 'passwordresettest@' . \config('app.domain'),
        ];

        $response = $this->post('/api/auth/password-reset/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
    }

    /**
     * Test password-reset/init with valid input
     *
     * @return array
     */
    public function testPasswordResetInitValidInput()
    {
        Queue::fake();

        // Assert that no jobs were pushed...
        Queue::assertNothingPushed();

        // Add required external email address to user settings
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $user->setSetting('external_email', 'ext@email.com');

        $data = [
            'email' => 'passwordresettest@' . \config('app.domain'),
        ];

        $response = $this->post('/api/auth/password-reset/init', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertNotEmpty($json['code']);

        // Assert the email sending job was pushed once
        Queue::assertPushed(\App\Jobs\PasswordResetEmail::class, 1);

        // Assert the job has proper data assigned
        Queue::assertPushed(\App\Jobs\PasswordResetEmail::class, function ($job) use ($user, &$code, $json) {
            $code = TestCase::getObjectProperty($job, 'code');

            return $code->user->id == $user->id && $code->code == $json['code'];
        });

        return [
            'code' => $code
        ];
    }

    /**
     * Test password-reset/verify with invalid input
     *
     * @return void
     */
    public function testPasswordResetVerifyInvalidInput()
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/password-reset/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Add verification code and required external email address to user settings
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        // Data with existing code but missing short_code
        $data = [
            'code' => $code->code,
        ];

        $response = $this->post('/api/auth/password-reset/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with invalid code
        $data = [
            'short_code' => '123456789',
            'code' => $code->code,
        ];

        $response = $this->post('/api/auth/password-reset/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // TODO: Test expired code
    }

    /**
     * Test password-reset/verify with valid input
     *
     * @return void
     */
    public function testPasswordResetVerifyValidInput()
    {
        // Add verification code and required external email address to user settings
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        // Data with invalid code
        $data = [
            'short_code' => $code->short_code,
            'code' => $code->code,
        ];

        $response = $this->post('/api/auth/password-reset/verify', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(1, $json);
        $this->assertSame('success', $json['status']);
    }

    /**
     * Test password-reset with invalid input
     *
     * @return void
     */
    public function testPasswordResetInvalidInput()
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        // Data with existing code but missing password
        $data = [
            'code' => $code->code,
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        // Data with existing code but wrong password confirmation
        $data = [
            'code' => $code->code,
            'short_code' => $code->short_code,
            'password' => 'password',
            'password_confirmation' => 'passwrong',
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        // Data with invalid short code
        $data = [
            'code' => $code->code,
            'short_code' => '123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);
    }

    /**
     * Test password reset with valid input
     *
     * @return void
     */
    public function testPasswordResetValidInput()
    {
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        Queue::fake();
        Queue::assertNothingPushed();

        $data = [
            'password' => 'test',
            'password_confirmation' => 'test',
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(4, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame('bearer', $json['token_type']);
        $this->assertTrue(!empty($json['expires_in']) && is_int($json['expires_in']) && $json['expires_in'] > 0);
        $this->assertNotEmpty($json['access_token']);

        Queue::assertPushed(\App\Jobs\UserUpdate::class, 1);
        Queue::assertPushed(\App\Jobs\UserUpdate::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->id == $user->id
                && $job_user->email == $user->email
                && $job_user->password_ldap != $user->password_ldap;
        });

        // Check if the code has been removed
        $this->assertNull(VerificationCode::find($code->code));

        // TODO: Check password before and after (?)

        // TODO: Check if the access token works
    }
}
