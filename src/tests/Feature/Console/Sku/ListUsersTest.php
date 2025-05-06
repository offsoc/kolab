<?php

namespace Tests\Feature\Console\Sku;

use App\Tenant;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $tenant = Tenant::where('title', 'kanarip.ch')->first();

        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases
        $code = \Artisan::call('sku:list-users domain-registration');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('', $output);

        $code = \Artisan::call('sku:list-users unknown');
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Unable to find the SKU.", $output);

        $code = \Artisan::call('sku:list-users 2fa');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("ned@kolab.org", $output);

        $code = \Artisan::call('sku:list-users mailbox');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $expected = [
            "fred@" . \config('app.domain'),
            "jack@kolab.org",
            "joe@kolab.org",
            "john@kolab.org",
            "ned@kolab.org",
        ];

        $this->assertSame(implode("\n", $expected), $output);

        $code = \Artisan::call('sku:list-users domain-hosting');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("john@kolab.org", $output);

        // Another tenant
        $code = \Artisan::call("sku:list-users mailbox --tenant={$tenant->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("user@kanarip.ch.dev-local", $output);
    }
}
