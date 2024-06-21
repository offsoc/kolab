<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
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
     *
     * @group ldap
     */
    public function testHandle(): void
    {
        $group = $this->getTestGroup('group@kolab.org', [
                'members' => [],
                'status' => Group::STATUS_NEW
        ]);

        // create to domain first
        $job = new \App\Jobs\Group\CreateJob($group->id);
        $job->handle();

        $this->assertTrue($group->fresh()->isLdapReady());

        Queue::fake();

        $job = new \App\Jobs\Group\DeleteJob($group->id);
        $job->handle();

        $group->refresh();

        $this->assertFalse($group->isLdapReady());
        $this->assertTrue($group->isDeleted());
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
        $job = new \App\Jobs\Group\DeleteJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Group 123 could not be found in the database.", $job->failureMessage);
    }
}
