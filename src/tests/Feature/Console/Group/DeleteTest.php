<?php

namespace Tests\Feature\Console\Group;

use App\Group;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group-test@kolabnow.com');
        $this->deleteTestUser('group-owner@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolabnow.com');
        $this->deleteTestUser('group-owner@kolabnow.com');

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
        $code = \Artisan::call("group:delete test@group.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Group test@group.com does not exist.", $output);

        $user = $this->getTestUser('group-owner@kolabnow.com');
        $group = $this->getTestGroup('group-test@kolabnow.com');

        // Existing group
        $code = \Artisan::call("group:delete {$group->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertTrue($group->refresh()->trashed());
    }
}
