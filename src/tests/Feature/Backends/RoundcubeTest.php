<?php

namespace Tests\Feature\Backends;

use App\Backends\Roundcube;
use Tests\TestCase;

class RoundcubeTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('roundcube@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('roundcube@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test creating a Roundcube user record (and related data)
     *
     * @group roundcube
     */
    public function testUserCreation(): void
    {
        $user = $this->getTestUser('roundcube@' . \config('app.domain'));
        $user->setSetting('first_name', 'First');
        $user->setSetting('last_name', 'Last');

        $db = Roundcube::dbh();

        // delete the user record if exists
        if ($userid = Roundcube::userId($user->email, false)) {
            $db->table('users')->delete();
        }

        // Create the user
        $userid = Roundcube::userId($user->email);

        $rcuser = $db->table('users')->where('username', $user->email)->first();

        $this->assertTrue(!empty($rcuser));

        $rcidentity = $db->table('identities')->where('user_id', $rcuser->user_id)->first();

        $this->assertSame($user->email, $rcidentity->email);
        $this->assertSame('First Last', $rcidentity->name);
        $this->assertSame(1, $rcidentity->standard);
    }
}
