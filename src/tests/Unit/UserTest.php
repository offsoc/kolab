<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * Test basic User funtionality
     */
    public function testUserStatus()
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
    public function testUserStatusInvalid(): void
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
