<?php

namespace Tests\Feature\Console\Domain;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@domain-delete.com');
        $this->deleteTestDomain('domain-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@domain-delete.com');
        $this->deleteTestDomain('domain-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing domain
        $code = \Artisan::call("domain:delete unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        // Public domain
        $code = \Artisan::call("domain:delete " . \config('app.domain'));
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("This domain is a public registration domain.", $output);

        // Create a user account for delete
        $user = $this->getTestUser('user@domain-delete.com');
        $domain = $this->getTestDomain('domain-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);

        // Non-deleted domain
        $code = \Artisan::call("domain:delete domain-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertTrue($domain->fresh()->trashed());
        $this->assertFalse($user->fresh()->trashed());

        // Deleted domain
        $code = \Artisan::call("domain:delete domain-delete.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);
    }
}
