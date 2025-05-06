<?php

namespace Tests\Feature\Console\Domain;

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

        // Non-existing domain
        $code = \Artisan::call("domain:restore unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        // Create a user account for delete
        $user = $this->getTestUser('user@force-delete.com');
        $domain = $this->getTestDomain('force-delete.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_HOSTED,
        ]);
        $package_kolab = Package::where('title', 'kolab')->first();
        $package_domain = Package::where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);
        $wallet = $user->wallets()->first();
        $entitlements = $wallet->entitlements->pluck('id')->all();

        $this->assertCount(8, $entitlements);

        // Non-deleted domain
        $code = \Artisan::call("domain:restore force-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("The domain is not yet deleted.", $output);

        $domain->delete();

        $this->assertTrue($domain->fresh()->trashed());

        // Deleted domain
        $code = \Artisan::call("domain:restore force-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertFalse($domain->fresh()->trashed());

        $user->delete();

        $this->assertTrue($domain->fresh()->trashed());
        $this->assertTrue($user->fresh()->trashed());

        // Deleted domain, deleted owner
        $code = \Artisan::call("domain:restore force-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("The domain owner is deleted.", $output);

        $this->assertTrue($domain->fresh()->trashed());
    }
}
