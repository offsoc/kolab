<?php

namespace Tests\Feature\Jobs\PGP;

use App\Support\Facades\PGP;
use Tests\TestCase;

class KeyDeleteTest extends TestCase
{
    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('john@kolab.org');

        PGP::shouldReceive('keyDelete')->once()->with($user, $user->email);

        $job = (new \App\Jobs\PGP\KeyDeleteJob($user->id, $user->email))->withFakeQueueInteractions();
        $job->handle();
        $job->assertNotFailed();
    }
}
