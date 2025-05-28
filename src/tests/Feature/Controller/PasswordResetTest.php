<?php

namespace Tests\Feature\Controller;

use App\IP4Net;
use App\Jobs\Mail\PasswordResetJob;
use App\Jobs\User\UpdateJob;
use App\User;
use App\VerificationCode;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('passwordresettest@' . \config('app.domain'));

        IP4Net::where('net_number', inet_pton('128.0.0.0'))->delete();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('passwordresettest@' . \config('app.domain'));

        IP4Net::where('net_number', inet_pton('128.0.0.0'))->delete();

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
        $data = ['email' => 'passwordresettest@' . \config('app.domain')];

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
        Queue::assertPushed(PasswordResetJob::class, 1);

        // Assert the job has proper data assigned
        Queue::assertPushed(PasswordResetJob::class, static function ($job) use ($user, &$code, $json) {
            $code = TestCase::getObjectProperty($job, 'code');

            return $code->user->id == $user->id && $code->code == $json['code'];
        });

        return [
            'code' => $code,
        ];
    }

    /**
     * Test password-reset/init with geo-lockin
     */
    public function testPasswordResetInitGeoLock(): void
    {
        Queue::fake();

        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $user->setSetting('limit_geo', json_encode(['US']));
        $user->setSetting('external_email', 'ext@email.com');

        $headers['X-Client-IP'] = '128.0.0.2';
        $post = ['email' => 'passwordresettest@' . \config('app.domain')];

        $response = $this->withHeaders($headers)->post('/api/auth/password-reset/init', $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame("The request location is not allowed.", $json['errors']['email']);

        IP4Net::create([
            'net_number' => '128.0.0.0',
            'net_broadcast' => '128.255.255.255',
            'net_mask' => 8,
            'country' => 'US',
            'rir_name' => 'test',
            'serial' => 1,
        ]);

        $response = $this->withHeaders($headers)->post('/api/auth/password-reset/init', $post);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertNotEmpty($json['code']);
    }

    /**
     * Test password-reset/verify with invalid input
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
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame($user->id, $json['userId']);
    }

    /**
     * Test password-reset with invalid input
     */
    public function testPasswordResetInvalidInput()
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/password-reset', $data);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('code', $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        // Data with existing code but missing password
        $data = [
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $response->assertStatus(422);

        $json = $response->json();

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

        // Data with existing code but password too short
        $data = [
            'code' => $code->code,
            'short_code' => $code->short_code,
            'password' => 'pas',
            'password_confirmation' => 'pas',
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
     */
    public function testPasswordResetValidInput()
    {
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        Queue::fake();
        Queue::assertNothingPushed();

        $data = [
            'password' => 'testtest',
            'password_confirmation' => 'testtest',
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/password-reset', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertSame('success', $json['status']);
        $this->assertSame('bearer', $json['token_type']);
        $this->assertTrue(!empty($json['expires_in']) && is_int($json['expires_in']) && $json['expires_in'] > 0);
        $this->assertNotEmpty($json['access_token']);
        $this->assertSame($user->email, $json['email']);
        $this->assertSame($user->id, $json['id']);

        Queue::assertPushed(UpdateJob::class, 1);

        Queue::assertPushed(
            UpdateJob::class,
            static function ($job) use ($user) {
                $userEmail = TestCase::getObjectProperty($job, 'userEmail');
                $userId = TestCase::getObjectProperty($job, 'userId');

                return $userEmail == $user->email && $userId == $user->id;
            }
        );

        // Check if the code has been removed
        $this->assertNull(VerificationCode::find($code->code));

        // TODO: Check password before and after (?)

        // TODO: Check if the access token works
    }

    /**
     * Test creating a password verification code
     */
    public function testCodeCreate()
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->verificationcodes()->delete();

        $response = $this->actingAs($user)->post('/api/v4/password-reset/code', []);
        $response->assertStatus(200);

        $json = $response->json();

        $code = $user->verificationcodes()->first();

        $this->assertSame('success', $json['status']);
        $this->assertSame($code->code, $json['code']);
        $this->assertSame($code->short_code, $json['short_code']);
        $this->assertStringContainsString(now()->addHours(24)->toDateString(), $json['expires_at']);
    }

    /**
     * Test deleting a password verification code
     */
    public function testCodeDelete()
    {
        $user = $this->getTestUser('passwordresettest@' . \config('app.domain'));
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $john->verificationcodes()->delete();
        $jack->verificationcodes()->delete();

        $john_code = new VerificationCode(['mode' => 'password-reset']);
        $john->verificationcodes()->save($john_code);
        $jack_code = new VerificationCode(['mode' => 'password-reset']);
        $jack->verificationcodes()->save($jack_code);
        $user_code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($user_code);

        // Unauth access
        $response = $this->delete('/api/v4/password-reset/code/' . $user_code->code);
        $response->assertStatus(401);

        // Non-existing code
        $response = $this->actingAs($john)->delete('/api/v4/password-reset/code/123');
        $response->assertStatus(404);

        // Existing code belonging to another user not controlled by the acting user
        $response = $this->actingAs($john)->delete('/api/v4/password-reset/code/' . $user_code->code);
        $response->assertStatus(403);

        // Deleting owned code
        $response = $this->actingAs($john)->delete('/api/v4/password-reset/code/' . $john_code->code);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $john->verificationcodes()->count());
        $this->assertSame('success', $json['status']);
        $this->assertSame("Password reset code deleted successfully.", $json['message']);

        // Deleting code of another user owned by the acting user
        // also use short_code+code as input parameter
        $id = $jack_code->short_code . '-' . $jack_code->code;
        $response = $this->actingAs($john)->delete('/api/v4/password-reset/code/' . $id);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $jack->verificationcodes()->count());
        $this->assertSame('success', $json['status']);
        $this->assertSame("Password reset code deleted successfully.", $json['message']);
    }
}
