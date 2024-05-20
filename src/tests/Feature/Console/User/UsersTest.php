<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');

        parent::tearDown();
    }
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:users unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $code = \Artisan::call("user:users john@kolab.org --attr=email");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertCount(4, explode("\n", $output));
        $this->assertStringContainsString("john@kolab.org", $output);
        $this->assertStringContainsString("ned@kolab.org", $output);
        $this->assertStringContainsString("joe@kolab.org", $output);
        $this->assertStringContainsString("jack@kolab.org", $output);

        // Test behaviour with deleted users
        Queue::fake();

        // Note: User:users() uses entitlements to get the owned users,
        // so we add a single entitlement, then we can soft-delete the user
        $user = $this->getTestUser('user@force-delete.com');
        $storage = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $user->assignSku($storage, 1);
        $user->delete();

        $code = \Artisan::call("user:users {$user->email} --attr=email");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame('', trim($output));

        $code = \Artisan::call("user:users {$user->email} --with-deleted --attr=email");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$user->id} {$user->email}", trim($output));
    }
}
