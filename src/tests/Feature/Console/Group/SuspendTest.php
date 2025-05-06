<?php

namespace Tests\Feature\Console\Group;

use App\EventLog;
use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SuspendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group-test@kolabnow.com');
        EventLog::truncate();
    }

    protected function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolabnow.com');
        EventLog::truncate();

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("group:suspend unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Group not found.", $output);

        $group = $this->getTestGroup('group-test@kolabnow.com');

        // Test success (no --comment)
        $code = \Artisan::call("group:suspend {$group->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertTrue($group->fresh()->isSuspended());
        $event = EventLog::where('object_id', $group->id)->where('object_type', Group::class)->first();
        $this->assertNull($event->comment);
        $this->assertSame(EventLog::TYPE_SUSPENDED, $event->type);

        $group->unsuspend();
        EventLog::truncate();

        // Test success (no --comment)
        $code = \Artisan::call("group:suspend --comment=\"Test comment\" {$group->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertTrue($group->fresh()->isSuspended());
        $event = EventLog::where('object_id', $group->id)->where('object_type', Group::class)->first();
        $this->assertSame('Test comment', $event->comment);
        $this->assertSame(EventLog::TYPE_SUSPENDED, $event->type);
    }
}
