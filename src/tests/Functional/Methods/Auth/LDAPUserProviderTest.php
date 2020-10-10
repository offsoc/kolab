<?php

namespace Tests\Functional\Methods\Auth;

use App\Auth\LDAPUserProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Auth\LDAPUserProvider.
 */
class LDAPUserProviderTest extends TestCase
{
    /**
     * A user provider so that we only have to code this once.
     *
     * @var \App\Auth\LDAPUserProvider
     */
    private $userProvider;

    public function setUp(): void
    {
        parent::setup();

        $app = $this->createApplication();
        $this->userProvider = new LDAPUserProvider($app['hash'], \App\User::class);
    }

    public function testRetrieveByCredentialsNone()
    {
        $result = $this->userProvider->retrieveByCredentials(
            [
                'email' => 'nobody.owns@this.email.domain',
                'password' => 'any password will do'
            ]
        );

        $this->assertNull($result);
    }

    /**
     * Test
     */
    public function testRetrieveByCredentialsDomainOwnerSuccess()
    {
        $result = $this->userProvider->retrieveByCredentials(
            [
                'email' => $this->domainOwner->email,
                'password' => $this->userPassword
            ]
        );

        $this->assertNotNull($result);
        $this->assertInstanceOf(\App\User::class, $result);
    }

    public function testRetrieveByCredentialsDomainOwnerWrongPasswordSuccess()
    {
        $result = $this->userProvider->retrieveByCredentials(
            [
                'email' => $this->domainOwner->email,
                'password' => 'definitely not the one that was randomly generated'
            ]
        );

        $this->assertNotNull($result);
        $this->assertInstanceOf(\App\User::class, $result);
    }

    public function testValidateCredentials()
    {
        $result = $this->userProvider->validateCredentials(
            $this->domainOwner,
            [
                'email' => $this->domainOwner->email,
                'password' => $this->userPassword
            ]
        );

        $this->assertTrue($result);
    }

    public function testValidateCredentialsCaseSensitivity()
    {
        $result = $this->userProvider->validateCredentials(
            $this->domainOwner,
            [
                'email' => strtoupper($this->domainOwner->email),
                'password' => $this->userPassword
            ]
        );

        $this->assertTrue($result);
    }

    public function testValidateCredentialsWrongPassword()
    {
        $result = $this->userProvider->validateCredentials(
            $this->domainOwner,
            [
                'email' => $this->domainOwner->email,
                'password' => 'definitely not the one that was randomly generated'
            ]
        );

        $this->assertFalse($result);
    }

    public function testValidateCredentialsEmptyPassword()
    {
        DB::update("UPDATE users SET password = null WHERE id = ?", [$this->domainOwner->id]);

        $result = $this->userProvider->validateCredentials(
            $this->domainOwner->fresh(),
            [
                'email' => $this->domainOwner->email,
                'password' => $this->userPassword
            ]
        );

        $this->assertTrue($result);
    }

    public function testValidateCredentialsEmptyLDAPPassword()
    {
        DB::update("UPDATE users SET password_ldap = null WHERE id = ?", [$this->domainOwner->id]);

        $result = $this->userProvider->validateCredentials(
            $this->domainOwner->fresh(),
            [
                'email' => $this->domainOwner->email,
                'password' => $this->userPassword
            ]
        );

        $this->assertTrue($result);
    }

    public function testValidateCredentialsEmptyPasswords()
    {
        DB::update("UPDATE users SET password = null, password_ldap = null WHERE id = ?", [$this->domainOwner->id]);

        $result = $this->userProvider->validateCredentials(
            $this->domainOwner->fresh(),
            [
                'email' => $this->domainOwner->email,
                'password' => $this->userPassword
            ]
        );

        $this->assertFalse($result);
    }
}
