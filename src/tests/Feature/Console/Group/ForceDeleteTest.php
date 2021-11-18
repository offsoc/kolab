<?php

namespace Tests\Feature\Console\Group;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForceDeleteTest extends TestCase
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

        $group = $this->getTestGroup('group-test@kolabnow.com');

        // Non-existing group
        $code = \Artisan::call("group:force-delete test@group.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Group not found.", $output);

        // Non-deleted group
        $code = \Artisan::call("group:force-delete {$group->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The group is not yet deleted.", $output);

        $group->delete();
        $this->assertTrue($group->trashed());

        // Existing and deleted group
        $code = \Artisan::call("group:force-delete {$group->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertCount(
            0,
            Group::withTrashed()->where('email', $group->email)->get()
        );
    }
}
