<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\SignupController;
use App\SignupCode;
use App\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignupTest extends TestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $user = User::firstOrCreate(['email' => 'SignupControllerTest1@' . \config('app.domain')]);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        User::where('email', 'SignupLogin@' . \config('app.domain'))
            ->orWhere('email', 'SignupControllerTest1@' . \config('app.domain'))
            ->delete();

        parent::tearDown();
    }

    /**
     * Test signup initialization with invalid input
     *
     * @return void
     */
    public function testSignupInitInvalidInput()
    {
        // Empty input data
        $data = [];

        $response = $this->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertArrayHasKey('name', $json['errors']);

        // Data with missing name
        $data = [
            'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com',
            'password' => 'simple123',
            'password_confirmation' => 'simple123'
        ];

        $response = $this->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('name', $json['errors']);

        // Data with invalid email (but not phone number)
        $data = [
            'email' => '@example.org',
            'name' => 'Signup User',
            'password' => 'simple123',
            'password_confirmation' => 'simple123'
        ];

        $response = $this->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);

        // TODO: Test phone validation
    }

    /**
     * Test signup initialization with valid input
     *
     * @return array
     */
    public function testSignupInitValidInput()
    {
        Queue::fake();

        // Assert that no jobs were pushed...
        Queue::assertNothingPushed();

        $data = [
            'email' => 'testuser@external.com',
            'name' => 'Signup User',
            'password' => 'simple123',
            'password_confirmation' => 'simple123'
        ];

        $response = $this->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertNotEmpty($json['code']);

        // Assert the email sending job was pushed once
        Queue::assertPushed(\App\Jobs\SignupVerificationEmail::class, 1);

        // Assert the job has proper data assigned
        Queue::assertPushed(\App\Jobs\SignupVerificationEmail::class, function ($job) use ($data, $json) {
            // Access protected property
            $reflection = new \ReflectionClass($job);
            $code = $reflection->getProperty('code');
            $code->setAccessible(true);
            $code = $code->getValue($job);

            return $code->code === $json['code']
                && $code->data['email'] === $data['email']
                && $code->data['name'] === $data['name'];
        });

        return [
            'code' => $json['code'],
            'email' => $data['email'],
            'name' => $data['name'],
        ];
    }

    /**
     * Test signup code verification with invalid input
     *
     * @depends testSignupInitValidInput
     * @return void
     */
    public function testSignupVerifyInvalidInput(array $result)
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('code', $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with existing code but missing short_code
        $data = [
            'code' => $result['code'],
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with invalid short_code
        $data = [
            'code' => $result['code'],
            'short_code' => 'XXXX',
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // TODO: Test expired code
    }

    /**
     * Test signup code verification with valid input
     *
     * @depends testSignupInitValidInput
     *
     * @return array
     */
    public function testSignupVerifyValidInput(array $result)
    {
        $code = SignupCode::find($result['code']);
        $data = [
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(3, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame($result['email'], $json['email']);
        $this->assertSame($result['name'], $json['name']);

        return $result;
    }

    /**
     * Test last signup step with invalid input
     *
     * @depends testSignupVerifyValidInput
     * @return void
     */
    public function testSignupInvalidInput(array $result)
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        // Passwords do not match
        $data = [
            'login' => 'test',
            'password' => 'test',
            'password_confirmation' => 'test2',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);

        // Login too short
        $data = [
            'login' => '1',
            'password' => 'test',
            'password_confirmation' => 'test',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);

        // Login invalid
        $data = [
            'login' => 'żżżżż',
            'password' => 'test',
            'password_confirmation' => 'test',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);

        // Data with invalid short_code
        $data = [
            'login' => 'TestLogin',
            'password' => 'test',
            'password_confirmation' => 'test',
            'code' => $result['code'],
            'short_code' => 'XXXX',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);
    }

    /**
     * Test last signup step with valid input (user creation)
     *
     * @depends testSignupVerifyValidInput
     * @return void
     */
    public function testSignupValidInput(array $result)
    {
        $identity = \strtolower('SignupLogin@') . \config('app.domain');

        // Make sure the user does not exist (it may happen when executing
        // tests again after failure)
        User::where('email', $identity)->delete();

        $code = SignupCode::find($result['code']);
        $data = [
            'login' => 'SignupLogin',
            'password' => 'test',
            'password_confirmation' => 'test',
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(4, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame('bearer', $json['token_type']);
        $this->assertTrue(!empty($json['expires_in']) && is_int($json['expires_in']) && $json['expires_in'] > 0);
        $this->assertNotEmpty($json['access_token']);

        // Check if the code has been removed
        $this->assertNull(SignupCode::where($result['code'])->first());

        // Check if the user has been created
        $user = User::where('email', $identity)->first();

        $this->assertNotEmpty($user);
        $this->assertSame($identity, $user->email);
        $this->assertSame($result['name'], $user->name);

        // Check external email in user settings
        $this->assertSame($result['email'], $user->getSetting('external_email', 'not set'));

        // TODO: Check if the access token works
    }

    /**
     * List of email address validation cases for testValidateEmail()
     *
     * @return array Arguments for testValidateEmail()
     */
    public function dataValidateEmail()
    {
        // To access config from dataProvider method we have to refreshApplication() first
        $this->refreshApplication();
        $domain = \config('app.domain');

        return [
            // general cases (invalid)
            ['', false, 'validation.emailinvalid'],
            ['example.org', false, 'validation.emailinvalid'],
            ['@example.org', false, 'validation.emailinvalid'],
            ['test@localhost', false, 'validation.emailinvalid'],
            // general cases (valid)
            ['test@domain.tld', false, null],
            ['&@example.org', false, null],
            // kolab identity cases
            ['admin@' . $domain, true, 'validation.emailexists'],
            ['administrator@' . $domain, true, 'validation.emailexists'],
            ['sales@' . $domain, true, 'validation.emailexists'],
            ['root@' . $domain, true, 'validation.emailexists'],
            ['&@' . $domain, true, 'validation.emailinvalid'],
            ['testnonsystemdomain@invalid.tld', true, 'validation.emailinvalid'],
            // existing account
            ['SignupControllerTest1@' . $domain, true, 'validation.emailexists'],
            // valid for signup
            ['test.test@' . $domain, true, null],
            ['test_test@' . $domain, true, null],
            ['test-test@' . $domain, true, null],
        ];
    }

    /**
     * Signup email validation.
     *
     * Note: Technicly these are mostly unit tests, but let's keep it here for now.
     * FIXME: Shall we do a http request for each case?
     *
     * @dataProvider dataValidateEmail
     */
    public function testValidateEmail($email, $signup, $expected_result)
    {
        $method = new \ReflectionMethod('App\Http\Controllers\API\SignupController', 'validateEmail');
        $method->setAccessible(true);

        $is_phone = false;
        $result = $method->invoke(new SignupController(), $email, $signup);

        $this->assertSame($expected_result, $result);
    }
}
