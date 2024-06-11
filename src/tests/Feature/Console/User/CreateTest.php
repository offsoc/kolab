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
        $this->deleteTestUser('user@kolabnow.com');
        $this->deleteTestUser('admin@kolab.org');
        $this->deleteTestUser('reseller@unknown.domain.tld');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@kolab.org');
        $this->deleteTestUser('user@kolabnow.com');
        $this->deleteTestUser('admin@kolab.org');
        $this->deleteTestUser('reseller@unknown.domain.tld');

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

        // Invalid email
        $code = \Artisan::call("user:create jack..test@kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("jack..test@kolab.org: The specified email is invalid.", $output);

        // Non-existing domain
        $code = \Artisan::call("user:create jack@kolab");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("No such domain kolab.", $output);

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

        // Valid (user)
        $code = \Artisan::call("user:create user@kolab.org --package=kolab");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'user@kolab.org')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);
        $this->assertSame(1, $user->countEntitlementsBySku('mailbox'));
        $this->assertSame(1, $user->countEntitlementsBySku('groupware'));
        $this->assertSame(5, $user->countEntitlementsBySku('storage'));

        // Valid (admin)
        $code = \Artisan::call("user:create admin@kolab.org --role=admin --password=simple123");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'admin@kolab.org')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);
        $this->assertEquals($user->role, User::ROLE_ADMIN);

        // Valid (reseller)
        $code = \Artisan::call("user:create reseller@unknown.domain.tld --role=reseller --password=simple123");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'reseller@unknown.domain.tld')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);
        $this->assertEquals($user->role, User::ROLE_RESELLER);

        // Valid (public domain)
        $code = \Artisan::call("user:create user@kolabnow.com");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'user@kolabnow.com')->first();
        $this->assertSame(0, $code);
        $this->assertEquals($user->id, $output);

        // Invalid role
        $code = \Artisan::call("user:create unknwon@kolab.org --role=unknown");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Invalid role: unknown", $output);

        // Existing email (but with role-reseller)
        $code = \Artisan::call("user:create jack@kolab.org --role=reseller");
        $output = trim(\Artisan::output());
        $user = User::where('email', 'reseller@unknown.domain.tld')->first();
        $this->assertSame(1, $code);
        $this->assertEquals("Email address is already in use", $output);

        // TODO: Test a case where deleted user exists
    }
}
