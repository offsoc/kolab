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
        $group = $this->getTestGroup('group@kolab.org', ['members' => []]);

        $this->assertFalse($group->isLdapReady());

        $job = new \App\Jobs\Group\CreateJob($group->id);
        $job->handle();

        $this->assertTrue($group->fresh()->isLdapReady());

        // Test non-existing group ID
        $job = new \App\Jobs\Group\CreateJob(123);
        $job->handle();

        $this->assertTrue($job->isReleased());
        $this->assertFalse($job->hasFailed());
    }
}
