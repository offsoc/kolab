<?php

namespace Tests\Feature;

use App\AuthAttempt;
use Tests\TestCase;

class AuthAttemptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');

        parent::tearDown();
    }

    public function testRecord(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertSame($authAttempt->user_id, $user->id);
        $this->assertSame($authAttempt->ip, "10.0.0.1");
        $authAttempt->refresh();
        $this->assertSame($authAttempt->status, "NEW");

        $authAttempt2 = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertSame($authAttempt->id, $authAttempt2->id);

        $authAttempt3 = AuthAttempt::recordAuthAttempt($user, "10.0.0.2");
        $this->assertNotSame($authAttempt->id, $authAttempt3->id);
    }
}
