<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
use App\Jobs\Group\CreateJob;
use App\Support\Facades\LDAP;
use Tests\TestCase;

class CreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group@kolab.org');
    }

    protected function tearDown(): void
    {
        $this->deleteTestGroup('group@kolab.org');

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $group = $this->getTestGroup('group@kolab.org', ['members' => [], 'status' => Group::STATUS_NEW]);

        $this->assertFalse($group->isLdapReady());

        \config(['app.with_ldap' => true]);

        LDAP::shouldReceive('createGroup')->once()->with($group)->andReturn(true);

        $job = (new CreateJob($group->id))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        $group->refresh();

        $this->assertTrue($group->isActive());
        $this->assertTrue($group->isLdapReady());

        // Test non-existing group ID
        $job = (new CreateJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased(delay: 5);
    }
}
