<?php

namespace Tests\Unit;

use App\Entitlement;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    /**
     * Test basic cost mutator
     */
    public function testSetCostAttribute(): void
    {
        $ent = new Entitlement();

        // We probably don't have to test this, as phpstan warns us anyway

        $ent->cost = 1.1; // @phpstan-ignore-line
        $this->assertSame(1, $ent->cost);

        $ent->cost = 1.5; // @phpstan-ignore-line
        $this->assertSame(2, $ent->cost);

        $ent->cost = '10'; // @phpstan-ignore-line
        $this->assertSame(10, $ent->cost);
    }
}
