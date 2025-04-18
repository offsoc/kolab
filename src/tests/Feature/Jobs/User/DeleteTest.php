<?php

namespace Tests\Feature\Jobs\User;

use App\Backends\Roundcube;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('new-job-user@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('new-job-user@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group roundcube
     */
    public function testHandle(): void
    {
        Queue::fake();

        $rcdb = Roundcube::dbh();

        $user = $this->getTestUser('new-job-user@' . \config('app.domain'), [
            'status' => User::STATUS_ACTIVE | User::STATUS_IMAP_READY | User::STATUS_LDAP_READY,
        ]);
        $rcuser = Roundcube::userId($user->email);

        $this->assertTrue($user->isLdapReady());
        $this->assertTrue($user->isImapReady());
        $this->assertFalse($user->isDeleted());
        $this->assertNotNull($rcdb->table('users')->where('username', $user->email)->first());

        \config(['app.with_ldap' => true]);
        \config(['app.with_imap' => true]);

        // Test job failure (user not yet deleted)
        $job = (new \App\Jobs\User\DeleteJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("User {$user->id} is not deleted.");

        // Test job failure (user already deleted)
        $user->status |= User::STATUS_DELETED;
        $user->deleted_at = \now();
        $user->saveQuietly();

        $job = (new \App\Jobs\User\DeleteJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("User {$user->id} is already marked as deleted.");

        // Test success delete from LDAP, IMAP and Roundcube
        $user->status ^= User::STATUS_DELETED;
        $user->saveQuietly();

        $this->assertFalse($user->isDeleted());
        $this->assertTrue($user->trashed());

        Queue::fake();

        IMAP::shouldReceive('deleteUser')->once()->with($user)->andReturn(true);
        LDAP::shouldReceive('deleteUser')->once()->with($user)->andReturn(true);

        $job = (new \App\Jobs\User\DeleteJob($user->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $user->refresh();

        $this->assertFalse($user->isLdapReady());
        $this->assertFalse($user->isImapReady());
        $this->assertTrue($user->isDeleted());
        $this->assertNull($rcdb->table('users')->where('username', $user->email)->first());

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 0);

        /*
        if (\config('app.with_imap')) {
            Queue::assertPushed(\App\Jobs\IMAP\AclCleanupJob::class, 1);
            Queue::assertPushed(
                \App\Jobs\IMAP\AclCleanupJob::class,
                function ($job) use ($user) {
                    $ident = TestCase::getObjectProperty($job, 'ident');
                    $domain = TestCase::getObjectProperty($job, 'domain');
                    return $ident == $user->email && $domain === '';
                }
            );
        }
        */

        // TODO: Test partial execution, i.e. only IMAP or only LDAP
    }
}
