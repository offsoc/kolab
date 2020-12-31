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
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolabnow.com');

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

        $group = Group::create(['email' => 'group-test@kolabnow.com']);

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
        $this->assertSame(['member@gmail.com', 'member2@gmail.com'], $group->refresh()->members);

        // Add a member that already exists
        $code = \Artisan::call("group:add-member {$group->email} member@gmail.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("member@gmail.com: Already exists in the group.", $output);
        $this->assertSame(['member@gmail.com', 'member2@gmail.com'], $group->refresh()->members);
    }
}
