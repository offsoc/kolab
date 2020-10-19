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
        $code = \Artisan::call('sku:list-users meet');
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
        $this->assertSame("jack@kolab.org\njoe@kolab.org\njohn@kolab.org\nned@kolab.org", $output);

        $code = \Artisan::call('sku:list-users domain-hosting');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("john@kolab.org", $output);

        $sku = \App\Sku::where('title', 'meet')->first();
        $user = $this->getTestUser('sku-list-users@kolabnow.com');
        $user->assignSku($sku);

        $code = \Artisan::call('sku:list-users meet');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame($user->email, $output);

        $user->assignSku($sku);

        $code = \Artisan::call('sku:list-users meet');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame($user->email, $output);
    }
}
