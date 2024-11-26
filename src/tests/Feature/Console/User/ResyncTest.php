<?php

namespace Tests\Feature\Console\User;

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
        $this->deleteTestUser('user@incomplete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestUser('user@incomplete.com');

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
        $this->assertSame("{$user->email}: pushed (delete)", $output);
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
        $this->assertSame("{$user->email}: pushed (delete)", $output);
    }

    /**
     * Test the command (existing users)
     */
    public function testHandleExisting(): void
    {
        $user = $this->getTestUser('user@force-delete.com', [
            'status' => User::STATUS_LDAP_READY | User::STATUS_IMAP_READY | User::STATUS_ACTIVE,
        ]);

        Queue::fake();

        // Test a specific user
        $code = \Artisan::call("user:resync {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: pushed (resync)", $output);

        Queue::assertPushed(\App\Jobs\User\ResyncJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\ResyncJob::class, function ($job) use ($user) {
            $job_user_id = TestCase::getObjectProperty($job, 'userId');
            return $job_user_id === $user->id;
        });

        // Test a user that is not IMAP_READY
        $user = $this->getTestUser('user@incomplete.com', [
            'status' => User::STATUS_LDAP_READY | User::STATUS_ACTIVE,
        ]);
        $code = \Artisan::call("user:resync --created-only");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("{$user->email}: pushed (create)", $output);
        // TODO: Test other cases
    }
}
