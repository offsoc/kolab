<?php

namespace Tests\Feature;

use App\AuthAttempt;
use Tests\TestCase;

class AuthAttemptTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');

        parent::tearDown();
    }

    public function testRecord(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertEquals($authAttempt->user_id, $user->id);
        $this->assertEquals($authAttempt->ip, "10.0.0.1");
        $authAttempt->refresh();
        $this->assertEquals($authAttempt->status, "NEW");

        $authAttempt2 = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $this->assertEquals($authAttempt->id, $authAttempt2->id);

        $authAttempt3 = \App\AuthAttempt::recordAuthAttempt($user, "10.0.0.2");
        $this->assertNotEquals($authAttempt->id, $authAttempt3->id);
    }
}
