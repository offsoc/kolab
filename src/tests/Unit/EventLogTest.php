<?php

namespace Tests\Unit;

use App\EventLog;
use Tests\TestCase;

class EventLogTest extends TestCase
{
    /**
     * Test type mutator
     */
    public function testSetTypeAttribute(): void
    {
        $event = new EventLog();

        $this->expectException(\Exception::class);
        $event->type = -1;

        $this->expectException(\Exception::class);
        $event->type = 256;

        $this->expectException(\Exception::class);
        $event->type = 'abc'; // @phpstan-ignore-line

        $event->type = 2;
        $this->assertSame(20, $event->type);
    }
}
