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
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
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

        // Test success
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
        User::withTrashed()->whereNotIn('id', [$user->id])->forceDelete();

        // Test run for all deleted users
        $code = \Artisan::call("user:resync --deleted-only");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->email}: pushed", $output);

        // TODO: Test other cases (non-deleted users)
    }
}
