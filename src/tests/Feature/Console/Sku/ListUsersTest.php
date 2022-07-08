<?php

namespace Tests\Feature\Console\Sku;

use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('sku-list-users@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('sku-list-users@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
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
            "reseller@" . \config('app.domain')
        ];

        $this->assertSame(implode("\n", $expected), $output);

        $code = \Artisan::call('sku:list-users domain-hosting');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("john@kolab.org", $output);
    }
}
