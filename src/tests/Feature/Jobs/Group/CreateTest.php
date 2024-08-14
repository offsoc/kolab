<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
use Tests\TestCase;

class CreateTest extends TestCase
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
        $group = $this->getTestGroup('group@kolab.org', ['members' => [], 'status' => Group::STATUS_NEW]);

        $this->assertFalse($group->isLdapReady());

        $job = new \App\Jobs\Group\CreateJob($group->id);
        $job->handle();

        $group->refresh();

        if (!\config('app.with_ldap')) {
            $this->assertTrue($group->isActive());
        } else {
            $this->assertTrue($group->isLdapReady());
        }

        // Test non-existing group ID
        $this->expectException(\Exception::class);
        $job = new \App\Jobs\Group\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());
    }
}
