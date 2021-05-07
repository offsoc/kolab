<?php

namespace Tests\Feature\Jobs\Group;

use App\Backends\LDAP;
use App\Group;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

        // Test non-existing group ID
        $job = new \App\Jobs\Group\UpdateJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("Group 123 could not be found in the database.", $job->failureMessage);

        // Create the group
        $group = $this->getTestGroup('group@kolab.org', ['members' => []]);
        LDAP::createGroup($group);

        // Test if group properties (members) actually changed in LDAP
        $group->members = ['test1@gmail.com'];
        $group->status |= Group::STATUS_LDAP_READY;
        $group->save();

        $job = new \App\Jobs\Group\UpdateJob($group->id);
        $job->handle();

        $ldapGroup = LDAP::getGroup($group->email);
        $root_dn = \config('ldap.hosted.root_dn');

        $this->assertSame('uid=test1@gmail.com,ou=People,ou=kolab.org,' . $root_dn, $ldapGroup['uniquemember']);

        // Test that suspended group is removed from LDAP
        $group->suspend();

        $job = new \App\Jobs\Group\UpdateJob($group->id);
        $job->handle();

        $this->assertNull(LDAP::getGroup($group->email));

        // Test that unsuspended group is added back to LDAP
        $group->unsuspend();

        $job = new \App\Jobs\Group\UpdateJob($group->id);
        $job->handle();

        $ldapGroup = LDAP::getGroup($group->email);
        $this->assertSame($group->email, $ldapGroup['mail']);
        $this->assertSame('uid=test1@gmail.com,ou=People,ou=kolab.org,' . $root_dn, $ldapGroup['uniquemember']);
    }
}
