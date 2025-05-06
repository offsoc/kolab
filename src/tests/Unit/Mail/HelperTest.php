<?php

namespace Tests\Unit\Mail;

use App\EventLog;
use App\Mail\Helper;
use App\Mail\TrialEnd;
use App\SignupInvitation;
use App\Sku;
use App\Tenant;
use App\TenantSetting;
use App\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('mail-helper-test@kolabnow.com');
        TenantSetting::truncate();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('mail-helper-test@kolabnow.com');
        TenantSetting::truncate();

        parent::tearDown();
    }

    /**
     * Test Helper::sendMail()
     */
    public function testSendMail(): void
    {
        EventLog::truncate();
        Mail::fake();

        $tenant = Tenant::whereNotIn('id', [1])->first();
        $invitation = new SignupInvitation();
        $invitation->id = 'test';
        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, null, ['to' => 'to@test.com', 'cc' => 'cc@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, static function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasCc('cc@test.com')
                && $mail->hasFrom(\config('mail.sender.address'), \config('mail.sender.name'))
                && $mail->hasReplyTo(\config('mail.replyto.address'), \config('mail.replyto.name'));
        });

        // Test with a tenant (but no per-tenant settings)
        Mail::fake();

        $invitation->tenant_id = $tenant->id;
        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, $tenant->id, ['to' => 'to@test.com', 'cc' => 'cc@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, static function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasCc('cc@test.com')
                && $mail->hasFrom(\config('mail.sender.address'), \config('mail.sender.name'))
                && $mail->hasReplyTo(\config('mail.replyto.address'), \config('mail.replyto.name'));
        });

        // Test with a tenant (but with per-tenant settings)
        Mail::fake();

        $tenant->setSettings([
            'mail.sender.address' => 'from@test.com',
            'mail.sender.name' => 'from name',
            'mail.replyto.address' => 'replyto@test.com',
            'mail.replyto.name' => 'replyto name',
        ]);

        $mail = new \App\Mail\SignupInvitation($invitation);

        Helper::sendMail($mail, $tenant->id, ['to' => 'to@test.com']);

        Mail::assertSent(\App\Mail\SignupInvitation::class, 1);
        Mail::assertSent(\App\Mail\SignupInvitation::class, static function ($mail) {
            return $mail->hasTo('to@test.com')
                && $mail->hasFrom('from@test.com', 'from name')
                && $mail->hasReplyTo('replyto@test.com', 'replyto name');
        });

        // No EventLog entries up to this point
        $this->assertSame(0, EventLog::count());

        // Assert EventLog entry
        $user = $this->getTestUser('mail-helper-test@kolabnow.com');
        $mail = new TrialEnd($user);

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
        [$to, $cc] = Helper::userEmails($user);

        $this->assertNull($to);
        $this->assertSame([], $cc);

        [$to, $cc] = Helper::userEmails($user, true);

        $this->assertNull($to);
        $this->assertSame([], $cc);

        // User with no mailbox but with external email
        $user->setSetting('external_email', 'external@test.com');
        [$to, $cc] = Helper::userEmails($user);

        $this->assertSame('external@test.com', $to);
        $this->assertSame([], $cc);

        [$to, $cc] = Helper::userEmails($user, true);

        $this->assertSame('external@test.com', $to);
        $this->assertSame([], $cc);

        // User with mailbox and external email
        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $user->assignSku($sku);

        [$to, $cc] = Helper::userEmails($user);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);

        [$to, $cc] = Helper::userEmails($user, true);

        $this->assertSame($user->email, $to);
        $this->assertSame(['external@test.com'], $cc);

        // User with mailbox, but no external email
        $user->setSetting('external_email', null);
        [$to, $cc] = Helper::userEmails($user);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);

        [$to, $cc] = Helper::userEmails($user, true);

        $this->assertSame($user->email, $to);
        $this->assertSame([], $cc);

        // Use with mailbox, but not ready
        $user->setSetting('external_email', 'external@test.com');
        $user->status = User::STATUS_ACTIVE | User::STATUS_LDAP_READY;
        $user->save();

        [$to, $cc] = Helper::userEmails($user, true);

        $this->assertNull($to);
        $this->assertSame(['external@test.com'], $cc);
    }
}
