<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class SharedFoldersTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:shared-folders unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $code = \Artisan::call("user:shared-folders john@kolab.org --attr=name");
        $output = trim(\Artisan::output());

        $folder1 = $this->getTestSharedFolder('folder-event@kolab.org');
        $folder2 = $this->getTestSharedFolder('folder-contact@kolab.org');

        $this->assertSame(0, $code);
        $this->assertCount(2, explode("\n", $output));
        $this->assertStringContainsString("{$folder1->id} {$folder1->name}", $output);
        $this->assertStringContainsString("{$folder2->id} {$folder2->name}", $output);
    }
}
