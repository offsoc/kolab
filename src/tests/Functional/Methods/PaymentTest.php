<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Payment methods.
 */
class PaymentTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testWallet()
    {
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Relations\BelongsTo', (new \App\Payment())->wallet());
        $this->assertNull((new \App\Payment())->wallet);
    }
}
