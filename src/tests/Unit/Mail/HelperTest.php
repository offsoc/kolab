<?php

namespace Tests\Unit\Mail;

use App\Mail\Helper;
use Tests\TestCase;

class HelperTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->deleteTestUser('mail-helper-test@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('mail-helper-test@kolabnow.com');
        parent::tearDown();
    }

    /**
     * Test Helper::userEmails()
     */
    public function testUserEmails(): void
    {
        $user = $this->getTestUser('mail-helper-test@kolabnow.com');

        // User with no mailbox and no external email
        list($to, $cc) = Helper::userEmails($user);

        $this->assertSame(null, $to);
        $this->assertSame([], $cc);

        list($to, $cc) = Helper::userEmails($user, true);

        $this->assertSame(null, $to);
        $this->assertSame([], $cc);

        // User with no mailbox but with external email
        $user->setSetting('external_email', 'external@test.com');
        list($to, $cc) = Helper::userEmails($user);

        $this->assertSame('external@test.com', $to);
        $this->assertSame([], $cc);

        list($to, $cc) = Helper::userEmails($user, true);

        $this->assertSame('external@test.com', $to);
        $this->assertSame([], $cc);

        // User with mailbox and external email
        $sku = \App\Sku::where('title', 'mailbox')->first();
        $user->assignSku($sku);

        list($to, $cc) = Helper::userEmails($user);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);

        list($to, $cc) = Helper::userEmails($user, true);

        $this->assertSame($user->email, $to);
        $this->assertSame(['external@test.com'], $cc);

        // User with mailbox, but no external email
        $user->setSetting('external_email', null);
        list($to, $cc) = Helper::userEmails($user);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);

        list($to, $cc) = Helper::userEmails($user, true);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);
    }
}
