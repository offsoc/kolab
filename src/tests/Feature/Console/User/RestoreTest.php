<?php

namespace Tests\Feature\Console\User;

use App\Domain;
use App\Package;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RestoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:restore unknown@unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Create a user account for delete
        $user = $this->getTestUser('user@force-delete.com');
        $domain = $this->getTestDomain('force-delete.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_HOSTED,
        ]);
        $package_kolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);
        $wallet = $user->wallets()->first();
        $entitlements = $wallet->entitlements->pluck('id')->all();

        $this->assertCount(8, $entitlements);

        // Non-deleted user
        $code = \Artisan::call("user:restore {$user->email}");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("The user is not deleted.", $output);

        $user->delete();

        $this->assertTrue($user->trashed());
        $this->assertTrue($domain->fresh()->trashed());

        // Deleted user
        $code = \Artisan::call("user:restore {$user->email}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertFalse($user->fresh()->trashed());
        $this->assertFalse($domain->fresh()->trashed());
    }
}
