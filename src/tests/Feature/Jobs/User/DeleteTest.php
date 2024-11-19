<?php

namespace Tests\Feature\Jobs\User;

use App\Backends\Roundcube;
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
     * @group ldap
     * @group imap
     * @group roundcube
     */
    public function testHandle(): void
    {
        Queue::fake();

        $rcdb = Roundcube::dbh();

        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));
        $rcuser = Roundcube::userId($user->email);

        try {
            $job = new \App\Jobs\User\CreateJob($user->id);
            $job->handle();
        } catch (\Exception $e) {
            // Ignore "Attempted to release a manually executed job" exception
        }

        $user->refresh();

        $this->assertSame(\config('app.with_ldap'), $user->isLdapReady());
        $this->assertSame(\config('app.with_imap'), $user->isImapReady());
        $this->assertFalse($user->isDeleted());
        $this->assertNotNull($rcdb->table('users')->where('username', $user->email)->first());

        // Test job failure (user already deleted)
        $user->status |= User::STATUS_DELETED;
        $user->save();

        $job = new \App\Jobs\User\DeleteJob($user->id);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User {$user->id} is already marked as deleted.", $job->failureMessage);

        // Test success delete from LDAP, IMAP and Roundcube
        $user->status ^= User::STATUS_DELETED;
        $user->deleted_at = \now();
        $user->saveQuietly();

        $this->assertFalse($user->isDeleted());
        $this->assertTrue($user->trashed());

        Queue::fake();

        $job = new \App\Jobs\User\DeleteJob($user->id);
        $job->handle();

        $user->refresh();

        $this->assertFalse($job->hasFailed());
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
