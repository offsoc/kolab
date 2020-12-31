<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
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

        $job = new \App\Jobs\Group\DeleteJob($group->id);
        $job->handle();

        $group->refresh();

        $this->assertFalse($group->isLdapReady());
        $this->assertTrue($group->isDeleted());
    }
}
