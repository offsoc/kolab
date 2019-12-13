<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\SignupController;
use App\SignupCode;
use App\User;

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

        $user = User::firstOrCreate(
            [
                'email' => 'SignupControllerTest1@SignupControllerTest.com'
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'SignupControllerTest1@SignupControllerTest.com'
            ]
        );

        $user->delete();

        parent::tearDown();
    }

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

    public function testSignupInitValidInput()
    {
        $data = [
            'email' => 'UsersApiControllerTest1@UsersApiControllerTest.com',
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

        // TODO: Test verification email/sms

        return [
            'code' => $json['code'],
            'email' => $data['email'],
            'name' => $data['name'],
        ];
    }

    /**
     * @depends testSignupInitValidInput
     */
    public function testSignupVerifyInvalidInput(array $result)
    {
        // Empty data
        $data = [];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertArrayHasKey('code', $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with existing code but missing short_code
        $data = [
            'code' => $result['code'],
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with invalid short_code
        $data = [
            'code' => $result['code'],
            'short_code' => 'XXXX',
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // TODO: Test expired code
    }

    /**
     * @depends testSignupInitValidInput
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
    }

    public function testSignup()
    {
        // TODO
    }

    /**
     * List of email address validation cases for testValidateEmail()
     */
    public function dataValidateEmail()
    {
        $domain = 'example.org';

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
            // existing account
            ['SignupControllerTest1@SignupControllerTest.com', true, 'validation.emailexists'],
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
        $result = $method->invoke(new SignupController, $email, $signup);

        $this->assertSame($expected_result, $result);
    }
}
