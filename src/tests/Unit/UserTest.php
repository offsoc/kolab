<?php

namespace Tests\Unit;

use App\User;
use App\Wallet;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * Test User password mutator
     */
    public function testSetPasswordAttribute(): void
    {
        $user = new User(['email' => 'user@email.com']);

        $user->password = 'test';

        $ssh512 = "{SSHA512}7iaw3Ur350mqGo7jwQrpkj9hiYB3Lkc/iBml1JQODbJ"
            . "6wYX4oOHV+E+IvIh/1nsUNzLDBMxfqa2Ob1f1ACio/w==";

        $this->assertMatchesRegularExpression('/^\$2y\$04\$[0-9a-zA-Z\/.]{53}$/', $user->password);
        $this->assertSame($ssh512, $user->password_ldap);
    }

    /**
     * Test User password mutator
     */
    public function testSetPasswordLdapAttribute(): void
    {
        $user = new User(['email' => 'user@email.com']);

        $user->password_ldap = 'test';

        $ssh512 = "{SSHA512}7iaw3Ur350mqGo7jwQrpkj9hiYB3Lkc/iBml1JQODbJ"
            . "6wYX4oOHV+E+IvIh/1nsUNzLDBMxfqa2Ob1f1ACio/w==";

        $this->assertMatchesRegularExpression('/^\$2y\$04\$[0-9a-zA-Z\/.]{53}$/', $user->password);
        $this->assertSame($ssh512, $user->password_ldap);
    }

    /**
     * Test User password validation
     */
    public function testPasswordValidation(): void
    {
        $user = new User(['email' => 'user@email.com']);
        $user->password = 'test';

        $this->assertSame(true, $user->validateCredentials('user@email.com', 'test'));
        $this->assertSame(false, $user->validateCredentials('user@email.com', 'wrong'));
        $this->assertSame(true, $user->validateCredentials('User@Email.Com', 'test'));
        $this->assertSame(false, $user->validateCredentials('wrong', 'test'));

        // Ensure the fallback to the ldap_password works if the current password is empty
        $ssh512 = "{SSHA512}7iaw3Ur350mqGo7jwQrpkj9hiYB3Lkc/iBml1JQODbJ"
            . "6wYX4oOHV+E+IvIh/1nsUNzLDBMxfqa2Ob1f1ACio/w==";
        $ldapUser = new User(['email' => 'user2@email.com']);
        $ldapUser->setRawAttributes(['password' => '', 'password_ldap' => $ssh512, 'email' => 'user2@email.com']);
        $this->assertSame($ldapUser->password, '');
        $this->assertSame($ldapUser->password_ldap, $ssh512);

        $this->assertSame(true, $ldapUser->validateCredentials('user2@email.com', 'test', false));
        $ldapUser->delete();
    }

    /**
     * Test basic User funtionality
     */
    public function testStatus(): void
    {
        $statuses = [
            User::STATUS_NEW,
            User::STATUS_ACTIVE,
            User::STATUS_SUSPENDED,
            User::STATUS_DELETED,
            User::STATUS_IMAP_READY,
            User::STATUS_LDAP_READY,
            User::STATUS_DEGRADED,
            User::STATUS_RESTRICTED,
        ];

        $users = \App\Utils::powerSet($statuses);

        foreach ($users as $user_statuses) {
            $user = new User(
                [
                    'email' => 'user@email.com',
                    'status' => \array_sum($user_statuses),
                ]
            );

            $this->assertTrue($user->isNew() === in_array(User::STATUS_NEW, $user_statuses));
            $this->assertTrue($user->isActive() === in_array(User::STATUS_ACTIVE, $user_statuses));
            $this->assertTrue($user->isSuspended() === in_array(User::STATUS_SUSPENDED, $user_statuses));
            $this->assertTrue($user->isDeleted() === in_array(User::STATUS_DELETED, $user_statuses));
            $this->assertTrue($user->isLdapReady() === in_array(User::STATUS_LDAP_READY, $user_statuses));
            $this->assertTrue($user->isImapReady() === in_array(User::STATUS_IMAP_READY, $user_statuses));
            $this->assertTrue($user->isDegraded() === in_array(User::STATUS_DEGRADED, $user_statuses));
            $this->assertTrue($user->isRestricted() === in_array(User::STATUS_RESTRICTED, $user_statuses));
        }
    }

    /**
     * Test setStatusAttribute exception
     */
    public function testStatusInvalid(): void
    {
        $this->expectException(\Exception::class);

        $user = new User(
            [
                'email' => 'user@email.com',
                'status' => 1234567,
            ]
        );
    }
}
