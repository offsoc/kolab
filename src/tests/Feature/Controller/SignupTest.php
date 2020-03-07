<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\SignupController;
use App\Domain;
use App\SignupCode;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignupTest extends TestCase
{
    private $domain;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // TODO: Some tests depend on existence of individual and group plans,
        //       we should probably create plans here to not depend on that
        $this->domain = $this->getPublicDomain();

        $this->deleteTestUser("SignupControllerTest1@$this->domain");
        $this->deleteTestUser("signuplogin@$this->domain");
        $this->deleteTestUser("admin@external.com");

        $this->deleteTestDomain('external.com');
        $this->deleteTestDomain('signup-domain.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser("SignupControllerTest1@$this->domain");
        $this->deleteTestUser("signuplogin@$this->domain");
        $this->deleteTestUser("admin@external.com");

        $this->deleteTestDomain('external.com');
        $this->deleteTestDomain('signup-domain.com');

        parent::tearDown();
    }

    /**
     * Return a public domain for signup tests
     */
    private function getPublicDomain(): string
    {
        if (!$this->domain) {
            $this->refreshApplication();
            $public_domains = Domain::getPublicDomains();
            $this->domain = reset($public_domains);

            if (empty($this->domain)) {
                $this->domain = 'signup-domain.com';
                Domain::create([
                        'namespace' => $this->domain,
                        'status' => Domain::STATUS_ACTIVE,
                        'type' => Domain::TYPE_PUBLIC,
                ]);
            }
        }

        return $this->domain;
    }

    /**
     * Test fetching plans for signup
     *
     * @return void
     */
    public function testSignupPlans()
    {
        // Note: this uses plans that already have been seeded into the DB

        $response = $this->get('/api/auth/signup/plans');
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertCount(2, $json['plans']);
        $this->assertArrayHasKey('title', $json['plans'][0]);
        $this->assertArrayHasKey('name', $json['plans'][0]);
        $this->assertArrayHasKey('description', $json['plans'][0]);
        $this->assertArrayHasKey('button', $json['plans'][0]);
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
        $queue = Queue::fake();

        // Assert that no jobs were pushed...
        $queue->assertNothingPushed();

        $data = [
            'email' => 'testuser@external.com',
            'name' => 'Signup User',
            'plan' => 'individual',
        ];

        $response = $this->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertNotEmpty($json['code']);

        // Assert the email sending job was pushed once
        $queue->assertPushed(\App\Jobs\SignupVerificationEmail::class, 1);

        // Assert the job has proper data assigned
        $queue->assertPushed(\App\Jobs\SignupVerificationEmail::class, function ($job) use ($data, $json) {
            $code = TestCase::getObjectProperty($job, 'code');

            return $code->code === $json['code']
                && $code->data['plan'] === $data['plan']
                && $code->data['email'] === $data['email']
                && $code->data['name'] === $data['name'];
        });

        return [
            'code' => $json['code'],
            'email' => $data['email'],
            'name' => $data['name'],
            'plan' => $data['plan'],
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
        $this->assertCount(5, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame($result['email'], $json['email']);
        $this->assertSame($result['name'], $json['name']);
        $this->assertSame(false, $json['is_domain']);
        $this->assertTrue(is_array($json['domains']) && !empty($json['domains']));

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
        $this->assertCount(3, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);
        $this->assertArrayHasKey('domain', $json['errors']);

        $domain = $this->getPublicDomain();

        // Passwords do not match and missing domain
        $data = [
            'login' => 'test',
            'password' => 'test',
            'password_confirmation' => 'test2',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('password', $json['errors']);
        $this->assertArrayHasKey('domain', $json['errors']);

        $domain = $this->getPublicDomain();

        // Login too short
        $data = [
            'login' => '1',
            'domain' => $domain,
            'password' => 'test',
            'password_confirmation' => 'test',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);

        // Missing codes
        $data = [
            'login' => 'login-valid',
            'domain' => $domain,
            'password' => 'test',
            'password_confirmation' => 'test',
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertArrayHasKey('code', $json['errors']);
        $this->assertArrayHasKey('short_code', $json['errors']);

        // Data with invalid short_code
        $data = [
            'login' => 'TestLogin',
            'domain' => $domain,
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

        // Valid code, invalid login
        $code = SignupCode::find($result['code']);
        $data = [
            'login' => 'żżżżżż',
            'domain' => $domain,
            'password' => 'test',
            'password_confirmation' => 'test',
            'code' => $result['code'],
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/signup', $data);
        $json = $response->json();

        $response->assertStatus(422);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertArrayHasKey('login', $json['errors']);
    }

    /**
     * Test last signup step with valid input (user creation)
     *
     * @depends testSignupVerifyValidInput
     * @return void
     */
    public function testSignupValidInput(array $result)
    {
        $queue = Queue::fake();

        $domain = $this->getPublicDomain();
        $identity = \strtolower('SignupLogin@') . $domain;
        $code = SignupCode::find($result['code']);
        $data = [
            'login' => 'SignupLogin',
            'domain' => $domain,
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

        $queue->assertPushed(\App\Jobs\UserCreate::class, 1);
        $queue->assertPushed(\App\Jobs\UserCreate::class, function ($job) use ($data) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->email === \strtolower($data['login'] . '@' . $data['domain']);
        });

        // Check if the code has been removed
        $this->assertNull(SignupCode::where('code', $result['code'])->first());

        // Check if the user has been created
        $user = User::where('email', $identity)->first();

        $this->assertNotEmpty($user);
        $this->assertSame($identity, $user->email);
        $this->assertSame($result['name'], $user->name);

        // Check external email in user settings
        $this->assertSame($result['email'], $user->getSetting('external_email'));

        // TODO: Check SKUs/Plan

        // TODO: Check if the access token works
    }

    /**
     * Test signup for a group (custom domain) account
     *
     * @return void
     */
    public function testSignupGroupAccount()
    {
        $queue = Queue::fake();

        // Initial signup request
        $user_data = $data = [
            'email' => 'testuser@external.com',
            'name' => 'Signup User',
            'plan' => 'group',
        ];

        $response = $this->withoutMiddleware()->post('/api/auth/signup/init', $data);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertNotEmpty($json['code']);

        // Assert the email sending job was pushed once
        $queue->assertPushed(\App\Jobs\SignupVerificationEmail::class, 1);

        // Assert the job has proper data assigned
        $queue->assertPushed(\App\Jobs\SignupVerificationEmail::class, function ($job) use ($data, $json) {
            $code = TestCase::getObjectProperty($job, 'code');

            return $code->code === $json['code']
                && $code->data['plan'] === $data['plan']
                && $code->data['email'] === $data['email']
                && $code->data['name'] === $data['name'];
        });

        // Verify the code
        $code = SignupCode::find($json['code']);
        $data = [
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/signup/verify', $data);
        $result = $response->json();

        $response->assertStatus(200);
        $this->assertCount(5, $result);
        $this->assertSame('success', $result['status']);
        $this->assertSame($user_data['email'], $result['email']);
        $this->assertSame($user_data['name'], $result['name']);
        $this->assertSame(true, $result['is_domain']);
        $this->assertSame([], $result['domains']);

        // Final signup request
        $login = 'admin';
        $domain = 'external.com';
        $data = [
            'login' => $login,
            'domain' => $domain,
            'password' => 'test',
            'password_confirmation' => 'test',
            'code' => $code->code,
            'short_code' => $code->short_code,
        ];

        $response = $this->post('/api/auth/signup', $data);
        $result = $response->json();

        $response->assertStatus(200);
        $this->assertCount(4, $result);
        $this->assertSame('success', $result['status']);
        $this->assertSame('bearer', $result['token_type']);
        $this->assertTrue(!empty($result['expires_in']) && is_int($result['expires_in']) && $result['expires_in'] > 0);
        $this->assertNotEmpty($result['access_token']);

        $queue->assertPushed(\App\Jobs\DomainCreate::class, 1);
        $queue->assertPushed(\App\Jobs\DomainCreate::class, function ($job) use ($domain) {
            $job_domain = TestCase::getObjectProperty($job, 'domain');

            return $job_domain->namespace === $domain;
        });

        $queue->assertPushed(\App\Jobs\UserCreate::class, 1);
        $queue->assertPushed(\App\Jobs\UserCreate::class, function ($job) use ($data) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->email === $data['login'] . '@' . $data['domain'];
        });

        // Check if the code has been removed
        $this->assertNull(SignupCode::find($code->id));

        // Check if the user has been created
        $user = User::where('email', $login . '@' . $domain)->first();

        $this->assertNotEmpty($user);
        $this->assertSame($user_data['name'], $user->name);

        // Check domain record

        // Check external email in user settings
        $this->assertSame($user_data['email'], $user->getSetting('external_email'));

        // TODO: Check SKUs/Plan

        // TODO: Check if the access token works
    }

    /**
     * List of login/domain validation cases for testValidateLogin()
     *
     * @return array Arguments for testValidateLogin()
     */
    public function dataValidateLogin(): array
    {
        $domain = $this->getPublicDomain();

        return [
            // Individual account
            ['', $domain, false, ['login' => 'The login field is required.']],
            ['test123456', 'localhost', false, ['domain' => 'The specified domain is invalid.']],
            ['test123456', 'unknown-domain.org', false, ['domain' => 'The specified domain is invalid.']],
            ['test.test', $domain, false, null],
            ['test_test', $domain, false, null],
            ['test-test', $domain, false, null],
            ['admin', $domain, false, ['login' => 'The specified login is not available.']],
            ['administrator', $domain, false, ['login' => 'The specified login is not available.']],
            ['sales', $domain, false, ['login' => 'The specified login is not available.']],
            ['root', $domain, false, ['login' => 'The specified login is not available.']],

            // TODO existing (public domain) user
            // ['signuplogin', $domain, false, ['login' => 'The specified login is not available.']],

            // Domain account
            ['admin', 'kolabsys.com', true, null],
            ['testnonsystemdomain', 'invalid', true, ['domain' => 'The specified domain is invalid.']],
            ['testnonsystemdomain', '.com', true, ['domain' => 'The specified domain is invalid.']],

            // existing custom domain
            ['jack', 'kolab.org', true, ['domain' => 'The specified domain is not available.']],
        ];
    }

    /**
     * Signup login/domain validation.
     *
     * Note: Technically these include unit tests, but let's keep it here for now.
     * FIXME: Shall we do a http request for each case?
     *
     * @dataProvider dataValidateLogin
     */
    public function testValidateLogin($login, $domain, $external, $expected_result): void
    {
        $result = $this->invokeMethod(new SignupController(), 'validateLogin', [$login, $domain, $external]);

        $this->assertSame($expected_result, $result);
    }
}
