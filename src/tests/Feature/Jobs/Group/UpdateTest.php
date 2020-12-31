<?php

namespace Tests\Feature\Jobs\Group;

use App\Group;
use Tests\TestCase;

class UpdateTest extends TestCase
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

        $job = new \App\Jobs\Group\UpdateJob($group->id);
        $job->handle();

        // TODO: Test if group properties (members) actually changed in LDAP
        $this->assertTrue(true);
    }
}
