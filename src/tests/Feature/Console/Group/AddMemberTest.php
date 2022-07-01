<?php

namespace Tests\Feature\Console\Group;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AddMemberTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group-test@kolabnow.com');
        $this->deleteTestGroup('group-test@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolabnow.com');
        $this->deleteTestGroup('group-test@kolab.org');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases

        // Non-existing group
        $code = \Artisan::call("group:add-member test@group.com member@group.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Group test@group.com does not exist.", $output);

        $john = $this->getTestUser('john@kolab.org');
        $group = Group::create(['email' => 'group-test@kolabnow.com']);
        $group->assignToWallet($john->wallet());

        // Existing group, invalid member
        $code = \Artisan::call("group:add-member {$group->email} member");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("member: The specified email address is invalid.", $output);

        // Existing group
        $code = \Artisan::call("group:add-member {$group->email} member@gmail.com");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertSame(['member@gmail.com'], $group->refresh()->members);

        // Existing group
        $code = \Artisan::call("group:add-member {$group->email} member2@gmail.com");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertSame(['member2@gmail.com', 'member@gmail.com'], $group->refresh()->members);

        // Add a member that already exists
        $code = \Artisan::call("group:add-member {$group->email} member@gmail.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("member@gmail.com: Already exists in the group.", $output);
        $this->assertSame(['member2@gmail.com', 'member@gmail.com'], $group->refresh()->members);

        // Adding a local-domain member that does not exist
        $john = $this->getTestUser('john@kolab.org');
        $group = Group::create(['email' => 'group-test@kolab.org']);
        $group->assignToWallet($john->wallet());

        $code = \Artisan::call("group:add-member {$group->email} member-unknown@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("member-unknown@kolab.org: The specified email address does not exist.", $output);
        $this->assertSame([], $group->refresh()->members);
    }
}
