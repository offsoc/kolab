<?php

namespace Tests\Feature\Console\User;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@kolab.org');
        $this->deleteTestUser('admin@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@kolab.org');
        $this->deleteTestUser('admin@kolab.org');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases

        // Existing email
        $code = \Artisan::call("user:create jack@kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("jack@kolab.org: The specified email is not available.", $output);

        // Existing email (of a user alias)
        $code = \Artisan::call("user:create jack.daniels@kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("jack.daniels@kolab.org: The specified email is not available.", $output);

        // Public domain not allowed in the group email address
        $code = \Artisan::call("user:create user@kolabnow.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain kolabnow.com is public.", $output);

        // Valid
        $code = \Artisan::call("user:create user@kolab.org");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'user@kolab.org')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);

        // Valid
        $code = \Artisan::call("user:create admin@kolab.org --package=kolab --role=admin --password=simple123");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'admin@kolab.org')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);
        $this->assertEquals($user->role, "admin");
    }
}
