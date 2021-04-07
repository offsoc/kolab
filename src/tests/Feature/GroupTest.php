<?php

namespace Tests\Feature;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GroupTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-test@kolabnow.com');
        $this->deleteTestGroup('group-test@kolabnow.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-test@kolabnow.com');
        $this->deleteTestGroup('group-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Tests for Group::assignToWallet()
     */
    public function testAssignToWallet(): void
    {
        $user = $this->getTestUser('user-test@kolabnow.com');
        $group = $this->getTestGroup('group-test@kolabnow.com');

        $result = $group->assignToWallet($user->wallets->first());

        $this->assertSame($group, $result);
        $this->assertSame(1, $group->entitlement()->count());

        // Can't be done twice on the same group
        $this->expectException(\Exception::class);
        $result->assignToWallet($user->wallets->first());
    }

    /**
     * Test group status assignment and is*() methods
     */
    public function testStatus(): void
    {
        $group = new Group();

        $this->assertSame(false, $group->isNew());
        $this->assertSame(false, $group->isActive());
        $this->assertSame(false, $group->isDeleted());
        $this->assertSame(false, $group->isLdapReady());
        $this->assertSame(false, $group->isSuspended());

        $group->status = Group::STATUS_NEW;

        $this->assertSame(true, $group->isNew());
        $this->assertSame(false, $group->isActive());
        $this->assertSame(false, $group->isDeleted());
        $this->assertSame(false, $group->isLdapReady());
        $this->assertSame(false, $group->isSuspended());

        $group->status |= Group::STATUS_ACTIVE;

        $this->assertSame(true, $group->isNew());
        $this->assertSame(true, $group->isActive());
        $this->assertSame(false, $group->isDeleted());
        $this->assertSame(false, $group->isLdapReady());
        $this->assertSame(false, $group->isSuspended());

        $group->status |= Group::STATUS_LDAP_READY;

        $this->assertSame(true, $group->isNew());
        $this->assertSame(true, $group->isActive());
        $this->assertSame(false, $group->isDeleted());
        $this->assertSame(true, $group->isLdapReady());
        $this->assertSame(false, $group->isSuspended());

        $group->status |= Group::STATUS_DELETED;

        $this->assertSame(true, $group->isNew());
        $this->assertSame(true, $group->isActive());
        $this->assertSame(true, $group->isDeleted());
        $this->assertSame(true, $group->isLdapReady());
        $this->assertSame(false, $group->isSuspended());

        $group->status |= Group::STATUS_SUSPENDED;

        $this->assertSame(true, $group->isNew());
        $this->assertSame(true, $group->isActive());
        $this->assertSame(true, $group->isDeleted());
        $this->assertSame(true, $group->isLdapReady());
        $this->assertSame(true, $group->isSuspended());

        // Unknown status value
        $this->expectException(\Exception::class);
        $group->status = 111;
    }

    /**
     * Test creating a group
     */
    public function testCreate(): void
    {
        Queue::fake();

        $group = Group::create(['email' => 'GROUP-test@kolabnow.com']);

        $this->assertSame('group-test@kolabnow.com', $group->email);
        $this->assertRegExp('/^[0-9]{1,20}$/', $group->id);
        $this->assertSame([], $group->members);
        $this->assertTrue($group->isNew());
        $this->assertTrue($group->isActive());

        Queue::assertPushed(
            \App\Jobs\Group\CreateJob::class,
            function ($job) use ($group) {
                $groupEmail = TestCase::getObjectProperty($job, 'groupEmail');
                $groupId = TestCase::getObjectProperty($job, 'groupId');

                return $groupEmail === $group->email
                    && $groupId === $group->id;
            }
        );
    }

    /**
     * Test group deletion and force-deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-test@kolabnow.com');
        $group = $this->getTestGroup('group-test@kolabnow.com');
        $group->assignToWallet($user->wallets->first());

        $entitlements = \App\Entitlement::where('entitleable_id', $group->id);

        $this->assertSame(1, $entitlements->count());

        $group->delete();

        $this->assertTrue($group->fresh()->trashed());
        $this->assertSame(0, $entitlements->count());
        $this->assertSame(1, $entitlements->withTrashed()->count());

        $group->forceDelete();

        $this->assertSame(0, $entitlements->withTrashed()->count());
        $this->assertCount(0, Group::withTrashed()->where('id', $group->id)->get());

        Queue::assertPushed(\App\Jobs\Group\DeleteJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Group\DeleteJob::class,
            function ($job) use ($group) {
                $groupEmail = TestCase::getObjectProperty($job, 'groupEmail');
                $groupId = TestCase::getObjectProperty($job, 'groupId');

                return $groupEmail === $group->email
                    && $groupId === $group->id;
            }
        );
    }

    /**
     * Tests for Group::emailExists()
     */
    public function testEmailExists(): void
    {
        Queue::fake();

        $group = $this->getTestGroup('group-test@kolabnow.com');

        $this->assertFalse(Group::emailExists('unknown@domain.tld'));
        $this->assertTrue(Group::emailExists($group->email));

        $result = Group::emailExists($group->email, true);
        $this->assertSame($result->id, $group->id);

        $group->delete();

        $this->assertTrue(Group::emailExists($group->email));

        $result = Group::emailExists($group->email, true);
        $this->assertSame($result->id, $group->id);
    }

    /**
     * Tests for Group::suspend()
     */
    public function testSuspend(): void
    {
        Queue::fake();

        $group = $this->getTestGroup('group-test@kolabnow.com');
        $group->suspend();

        $this->assertTrue($group->isSuspended());

        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Group\UpdateJob::class,
            function ($job) use ($group) {
                $groupEmail = TestCase::getObjectProperty($job, 'groupEmail');
                $groupId = TestCase::getObjectProperty($job, 'groupId');

                return $groupEmail === $group->email
                    && $groupId === $group->id;
            }
        );
    }

    /**
     * Test updating a group
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $group = $this->getTestGroup('group-test@kolabnow.com');
        $group->status |= Group::STATUS_DELETED;
        $group->save();

        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Group\UpdateJob::class,
            function ($job) use ($group) {
                $groupEmail = TestCase::getObjectProperty($job, 'groupEmail');
                $groupId = TestCase::getObjectProperty($job, 'groupId');

                return $groupEmail === $group->email
                    && $groupId === $group->id;
            }
        );
    }

    /**
     * Tests for Group::unsuspend()
     */
    public function testUnsuspend(): void
    {
        Queue::fake();

        $group = $this->getTestGroup('group-test@kolabnow.com');
        $group->status = Group::STATUS_SUSPENDED;
        $group->unsuspend();

        $this->assertFalse($group->isSuspended());

        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Group\UpdateJob::class,
            function ($job) use ($group) {
                $groupEmail = TestCase::getObjectProperty($job, 'groupEmail');
                $groupId = TestCase::getObjectProperty($job, 'groupId');

                return $groupEmail === $group->email
                    && $groupId === $group->id;
            }
        );
    }
}
