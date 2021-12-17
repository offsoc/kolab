<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:users unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $code = \Artisan::call("user:users john@kolab.org --attr=email");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertCount(4, explode("\n", $output));
        $this->assertStringContainsString("john@kolab.org", $output);
        $this->assertStringContainsString("ned@kolab.org", $output);
        $this->assertStringContainsString("joe@kolab.org", $output);
        $this->assertStringContainsString("jack@kolab.org", $output);
    }
}
