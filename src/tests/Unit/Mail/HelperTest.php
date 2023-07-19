<?php

namespace Tests\Unit\Mail;

use App\EventLog;
use App\Mail\Helper;
use App\User;
use Illuminate\Support\Facades\Mail;
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
        \App\TenantSetting::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('mail-helper-test@kolabnow.com');
        \App\TenantSetting::truncate();

        parent::tearDown();
    }

    /**
     * Test Helper::sendMail()
     */
    public function testSendMail(): void
    {
        EventLog::truncate();
        Mail::fake();

        $tenant = \App\Tenant::whereNotIn('id', [1])->first();
        $invitation = new \App\SignupInvitation();
        $invitation->id = 'test';
        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, null, ['to' => 'to@test.com', 'cc' => 'cc@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasCc('cc@test.com')
                && $mail->hasFrom(\config('mail.from.address'), \config('mail.from.name'))
                && $mail->hasReplyTo(\config('mail.reply_to.address'), \config('mail.reply_to.name'));
        });

        // Test with a tenant (but no per-tenant settings)
        Mail::fake();

        $invitation->tenant_id = $tenant->id;
        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, $tenant->id, ['to' => 'to@test.com', 'cc' => 'cc@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasCc('cc@test.com')
                && $mail->hasFrom(\config('mail.from.address'), \config('mail.from.name'))
                && $mail->hasReplyTo(\config('mail.reply_to.address'), \config('mail.reply_to.name'));
        });

        // Test with a tenant (but with per-tenant settings)
        Mail::fake();

        $tenant->setSettings([
            'mail.from.address' => 'from@test.com',
            'mail.from.name' => 'from name',
            'mail.reply_to.address' => 'replyto@test.com',
            'mail.reply_to.name' => 'replyto name',
        ]);

        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, $tenant->id, ['to' => 'to@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasFrom('from@test.com', 'from name')
                && $mail->hasReplyTo('replyto@test.com', 'replyto name');
        });

        // No EventLog entries up to this point
        $this->assertSame(0, EventLog::count());

        // Assert EventLog entry
        $user = $this->getTestUser('mail-helper-test@kolabnow.com');
        $mail = new \App\Mail\TrialEnd($user);

        Helper::sendMail($mail, $tenant->id, ['to' => 'to@test.com', 'cc' => 'cc@test.com']);

        $event = EventLog::where('object_id', $user->id)->where('object_type', User::class)->first();
        $this->assertSame(EventLog::TYPE_MAILSENT, $event->type);
        $this->assertSame(['recipients' => ['to@test.com', 'cc@test.com']], $event->data);
        $this->assertSame("[TrialEnd] Kolab Now: Your trial phase has ended", $event->comment);

        // TODO: Test somehow exception case
    }

    /**
     * Test Helper::userEmails()
     */
    public function testUserEmails(): void
    {
        $status = User::STATUS_ACTIVE | User::STATUS_LDAP_READY | User::STATUS_IMAP_READY;
        $user = $this->getTestUser('mail-helper-test@kolabnow.com', ['status' => $status]);

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
        $sku = \App\Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
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

        // Use with mailbox, but not ready
        $user->setSetting('external_email', 'external@test.com');
        $user->status = User::STATUS_ACTIVE | User::STATUS_LDAP_READY;
        $user->save();

        list($to, $cc) = Helper::userEmails($user, true);

        $this->assertSame(null, $to);
        $this->assertSame(['external@test.com'], $cc);
    }
}
