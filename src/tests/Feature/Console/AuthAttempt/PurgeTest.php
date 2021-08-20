<?php

namespace Tests\Feature\Console\AuthAttempt;

use Carbon\Carbon;
use App\AuthAttempt;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        AuthAttempt::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        AuthAttempt::truncate();
        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases
        $cutoff = Carbon::now()->subDays(30);

        $user = $this->getTestUser('john@kolab.org');

        $authAttempt1 = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $authAttempt1->refresh();
        $authAttempt1->updated_at = $cutoff->copy()->addDays(1);
        $authAttempt1->save(['timestamps' => false]);

        $authAttempt2 = AuthAttempt::recordAuthAttempt($user, "10.0.0.2");
        $authAttempt2->refresh();
        $authAttempt2->updated_at = $cutoff->copy()->subDays(1);
        $authAttempt2->save(['timestamps' => false]);

        $code = \Artisan::call('authattempt:purge');
        $this->assertSame(0, $code);

        $list = AuthAttempt::all();
        $this->assertCount(1, $list);
        $this->assertSame($authAttempt1->id, $list[0]->id);
    }
}
