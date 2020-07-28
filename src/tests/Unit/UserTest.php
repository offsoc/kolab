<?php

namespace Tests\Unit;

use App\User;
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

        $this->assertRegExp('/^\$2y\$12\$[0-9a-zA-Z\/.]{53}$/', $user->password);
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

        $this->assertRegExp('/^\$2y\$12\$[0-9a-zA-Z\/.]{53}$/', $user->password);
        $this->assertSame($ssh512, $user->password_ldap);
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
