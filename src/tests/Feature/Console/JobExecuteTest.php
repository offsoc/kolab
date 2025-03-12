<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class JobExecuteTest extends TestCase
{
    /**
     * Test command execution
     */
    public function testHandle(): void
    {
        // Object that does not exist
        $code = \Artisan::call("job:execute User/UpdateJob unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Object not found.", $output);

        // Invalid job name
        $code = \Artisan::call("job:execute Job john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Invalid or unsupported job name.", $output);

        // Object that exists
        $code = \Artisan::call("job:execute User/UpdateJob john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
    }
}
