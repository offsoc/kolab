<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
use App\Support\Facades\LDAP;
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

        $this->deleteTestGroup('group@kolab.org');
    }

    public function tearDown(): void
    {
        $this->deleteTestGroup('group@kolab.org');

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $group = $this->getTestGroup('group@kolab.org', [
                'members' => [],
                'status' => Group::STATUS_NEW | Group::STATUS_LDAP_READY
        ]);

        \config(['app.with_ldap' => true]);

        $this->assertTrue($group->isLdapReady());
        $this->assertFalse($group->isDeleted());

        // Test group that is not deleted yet
        $job = (new \App\Jobs\Group\DeleteJob($group->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Group {$group->id} is not deleted.");

        $group->deleted_at = \now();
        $group->saveQuietly();

        // Deleted group (expect success)
        Queue::fake();
        LDAP::shouldReceive('deleteGroup')->once()->with($group)->andReturn(true);

        $job = (new \App\Jobs\Group\DeleteJob($group->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $group->refresh();

        $this->assertFalse($group->isLdapReady());
        $this->assertTrue($group->isDeleted());

        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 0);
/*
        Queue::assertPushed(\App\Jobs\IMAP\AclCleanupJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\IMAP\AclCleanupJob::class,
            function ($job) {
                $ident = TestCase::getObjectProperty($job, 'ident');
                $domain = TestCase::getObjectProperty($job, 'domain');
                return $ident == 'group' && $domain === 'kolab.org';
            }
        );
*/
        // Test non-existing group ID
        $job = (new \App\Jobs\Group\DeleteJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("Group 123 could not be found in the database.");
    }
}
