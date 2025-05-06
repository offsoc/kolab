<?php

namespace Tests\Feature\Console\User;

use App\Domain;
use App\Package;
use Tests\TestCase;

class AddAliasTest extends TestCase
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
        // Non-existing user
        $code = \Artisan::call("user:add-alias unknown unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('user@force-delete.com');
        $domain = $this->getTestDomain('force-delete.com', [
            'status' => Domain::STATUS_NEW,
            'type' => Domain::TYPE_HOSTED,
        ]);
        $package_kolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);

        // Invalid alias
        $code = \Artisan::call("user:add-alias {$user->email} invalid");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The specified alias is invalid.", $output);

        // Test success
        $code = \Artisan::call("user:add-alias {$user->email} test@force-delete.com");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertCount(1, $user->aliases()->where('alias', 'test@force-delete.com')->get());

        // Alias already exists
        $code = \Artisan::call("user:add-alias {$user->email} test@force-delete.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Address is already assigned to the user.", $output);

        // TODO: test --force option
    }
}
