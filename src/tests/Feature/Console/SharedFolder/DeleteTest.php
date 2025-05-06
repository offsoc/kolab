<?php

namespace Tests\Feature\Console\SharedFolder;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestSharedFolder('folder-test@kolabnow.com');
        $this->deleteTestUser('folder-owner@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestSharedFolder('folder-test@kolabnow.com');
        $this->deleteTestUser('folder-owner@kolabnow.com');

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

        // Non-existing folder
        $code = \Artisan::call("sharedfolder:delete test@folder.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Shared folder test@folder.com does not exist.", $output);

        $user = $this->getTestUser('folder-owner@kolabnow.com');
        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        // Existing folder
        $code = \Artisan::call("sharedfolder:delete {$folder->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertTrue($folder->refresh()->trashed());
    }
}
