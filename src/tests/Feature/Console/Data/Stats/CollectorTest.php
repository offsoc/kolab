<?php

namespace Tests\Feature\Console\Data\Stats;

use App\Http\Controllers\API\V4\Admin\StatsController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('stats')->truncate();
        DB::table('transactions')->truncate();
    }

    protected function tearDown(): void
    {
        DB::table('stats')->truncate();
        DB::table('transactions')->truncate();

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
        $wallet->award(1000);

        $code = \Artisan::call("data:stats:collector");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);

        $stats = DB::table('stats')->get();

        $this->assertSame(1, $stats->count());
        $this->assertSame(StatsController::TYPE_PAYERS, $stats[0]->type);
        $this->assertSame((int) \config('app.tenant_id'), (int) $stats[0]->tenant_id);
        $this->assertSame(4, $stats[0]->value); // there's 4 users in john's wallet

        // TODO: More precise tests (degraded users)
    }
}
