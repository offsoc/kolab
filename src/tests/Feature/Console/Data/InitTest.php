<?php

namespace Tests\Feature\Console\Data;

use Tests\TestCase;

class InitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void {}

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("data:init");
        $this->assertSame(0, $code);
    }
}
