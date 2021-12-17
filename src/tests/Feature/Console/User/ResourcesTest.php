<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class ResourcesTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:resources unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $code = \Artisan::call("user:resources john@kolab.org --attr=name");
        $output = trim(\Artisan::output());

        $resource1 = $this->getTestResource('resource-test1@kolab.org');
        $resource2 = $this->getTestResource('resource-test2@kolab.org');

        $this->assertSame(0, $code);
        $this->assertCount(2, explode("\n", $output));
        $this->assertStringContainsString("{$resource1->id} {$resource1->name}", $output);
        $this->assertStringContainsString("{$resource2->id} {$resource2->name}", $output);
    }
}
