<?php

namespace Tests\Feature\Jobs\PGP;

use App\Support\Facades\PGP;
use App\UserAlias;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KeyCreateTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        UserAlias::where('alias', 'test-alias@kolab.org')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        UserAlias::where('alias', 'test-alias@kolab.org')->delete();

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');

        // Test without an alias
        PGP::shouldReceive('keypairCreate')->once()->with($user, $user->email);

        $job = (new \App\Jobs\PGP\KeyCreateJob($user->id, $user->email))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();

        // Test with an alias
        $alias = UserAlias::create(['user_id' => $user->id, 'alias' => 'test-alias@kolab.org']);
        PGP::shouldReceive('keypairCreate')->once()->with($user, $alias->alias);

        $job = (new \App\Jobs\PGP\KeyCreateJob($user->id, 'test-alias@kolab.org'))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();
    }
}
