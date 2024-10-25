<?php

namespace Tests\Feature\Console\DB;

use Tests\TestCase;

class ExpungeTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("db:expunge");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
    }
}
