<?php

namespace Tests\Feature\Console\User;

use App\Domain;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResyncTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command (deleted and non-existing users)
     */
    public function testHandleDeleted(): void
    {
        // Non-existing user
        $code = \Artisan::call("user:resync unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('user@force-delete.com');
        User::where('id', $user->id)->update([
                'deleted_at' => now(),
                'status' => User::STATUS_DELETED | User::STATUS_IMAP_READY,
        ]);

        Queue::fake();

        // Test success (--dry-run)
        $code = \Artisan::call("user:resync {$user->email} --dry-run");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: will be pushed", $output);
        $this->assertTrue($user->fresh()->isDeleted());

        Queue::assertNothingPushed();

        // Test success (deleted user)
        $code = \Artisan::call("user:resync {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: pushed", $output);
        $user->refresh();
        $this->assertFalse($user->isDeleted());
        $this->assertTrue($user->isImapReady());

        Queue::assertPushed(\App\Jobs\User\DeleteJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\DeleteJob::class, function ($job) use ($user) {
            $job_user_id = TestCase::getObjectProperty($job, 'userId');
            return $job_user_id === $user->id;
        });

        Queue::fake();
        User::withTrashed()->where('id', $user->id)->update(['status' => User::STATUS_DELETED]);

        // Test nothing to be done (deleted user)
        $code = \Artisan::call("user:resync {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: in-sync", $output);
        Queue::assertNothingPushed();

        Queue::fake();
        User::withTrashed()->where('id', $user->id)->update([
                'status' => User::STATUS_DELETED | User::STATUS_IMAP_READY
        ]);

        // Remove all deleted users except one, to not interfere
        User::withTrashed()->whereNotNull('deleted_at')->whereNotIn('id', [$user->id])->forceDelete();

        // Test run for all deleted users
        $code = \Artisan::call("user:resync --deleted-only");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: pushed", $output);
    }

    /**
     * Test the command (existing users)
     *
     * @group ldap
     * @group imap
     */
    public function testHandleExisting(): void
    {
        $domain = $this->getTestDomain('force-delete.com', [
            'status' => Domain::STATUS_LDAP_READY | Domain::STATUS_ACTIVE,
            'type' => Domain::TYPE_EXTERNAL,
        ]);

        $user = $this->getTestUser('user@force-delete.com', [
            'status' => User::STATUS_LDAP_READY | User::STATUS_IMAP_READY | User::STATUS_ACTIVE,
        ]);

        Queue::fake();

        // Test a user (and custom domain) that both aren't in ldap (despite their status)
        $code = \Artisan::call("user:resync {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: pushed", $output);
        $user->refresh();
        $domain->refresh();
        $this->assertFalse($user->isLdapReady());
        $this->assertFalse($user->isImapReady());
        $this->assertFalse($domain->isLdapReady());

        Queue::assertPushed(\App\Jobs\User\CreateJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\CreateJob::class, function ($job) use ($user) {
            $job_user_id = TestCase::getObjectProperty($job, 'userId');
            return $job_user_id === $user->id;
        });
        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);
        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, function ($job) use ($domain) {
            $job_domain_id = TestCase::getObjectProperty($job, 'domainId');
            return $job_domain_id === $domain->id;
        });

        // TODO: Test other cases
    }
}
