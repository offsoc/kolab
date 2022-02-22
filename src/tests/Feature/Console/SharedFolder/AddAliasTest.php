<?php

namespace Tests\Feature\Console\SharedFolder;

use Tests\TestCase;

class AddAliasTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestSharedFolder('folder-test@force-delete.com');
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestSharedFolder('folder-test@force-delete.com');
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        // Non-existing folder
        $code = \Artisan::call("sharedfolder:add-alias unknown unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Folder not found.", $output);

        $user = $this->getTestUser('user@force-delete.com');
        $domain = $this->getTestDomain('force-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_EXTERNAL,
        ]);
        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);
        $folder = $this->getTestSharedFolder('folder-test@force-delete.com');
        $folder->assignToWallet($user->wallets->first());

        // Invalid alias
        $code = \Artisan::call("sharedfolder:add-alias {$folder->email} invalid");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The specified alias is invalid.", $output);
        $this->assertSame([], $folder->aliases()->pluck('alias')->all());

        // A domain of another user
        $code = \Artisan::call("sharedfolder:add-alias {$folder->email} test@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The specified domain is not available.", $output);
        $this->assertSame([], $folder->aliases()->pluck('alias')->all());

        // Test success
        $code = \Artisan::call("sharedfolder:add-alias {$folder->email} test@force-delete.com");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertSame(['test@force-delete.com'], $folder->aliases()->pluck('alias')->all());

        // Alias already exists
        $code = \Artisan::call("sharedfolder:add-alias {$folder->email} test@force-delete.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Address is already assigned to the folder.", $output);

        // TODO: test --force option
    }
}
