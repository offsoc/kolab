<?php

namespace Tests\Feature\Console\SharedFolder;

use App\SharedFolder;
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

        $this->deleteTestSharedFolder('folder-test@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestSharedFolder('folder-test@kolabnow.com');

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

        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        // Non-existing folder
        $code = \Artisan::call("sharedfolder:force-delete test@folder.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Shared folder not found.", $output);

        // Non-deleted folder
        $code = \Artisan::call("sharedfolder:force-delete {$folder->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The shared folder is not yet deleted.", $output);

        $folder->delete();
        $this->assertTrue($folder->trashed());

        // Existing and deleted folder
        $code = \Artisan::call("sharedfolder:force-delete {$folder->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertCount(
            0,
            SharedFolder::withTrashed()->where('email', $folder->email)->get()
        );
    }
}
