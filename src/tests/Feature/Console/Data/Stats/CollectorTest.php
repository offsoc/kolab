<?php

namespace Tests\Feature\Console\Data\Stats;

use App\Http\Controllers\API\V4\Admin\StatsController;
use App\Payment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectorTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        DB::table('stats')->truncate();
        DB::table('payments')->truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        DB::table('stats')->truncate();
        DB::table('payments')->truncate();

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("data:stats:collector");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);

        $stats = DB::table('stats')->get();

        $this->assertSame(0, $stats->count());

        $john = $this->getTestUser('john@kolab.org');
        $wallet = $john->wallet();

        \App\Payment::create([
                'id' => 'test1',
                'description' => '',
                'status' => Payment::STATUS_PAID,
                'amount' => 1000,
                'credit_amount' => 1000,
                'type' => Payment::TYPE_ONEOFF,
                'wallet_id' => $wallet->id,
                'provider' => 'mollie',
                'currency' => $wallet->currency,
                'currency_amount' => 1000,
        ]);

        $code = \Artisan::call("data:stats:collector");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);

        $stats = DB::table('stats')->get();

        $this->assertSame(1, $stats->count());
        $this->assertSame(StatsController::TYPE_PAYERS, $stats[0]->type);
        $this->assertEquals(\config('app.tenant_id'), $stats[0]->tenant_id);
        $this->assertEquals(4, $stats[0]->value); // there's 4 users in john's wallet

        // TODO: More precise tests (degraded users)
    }
}
