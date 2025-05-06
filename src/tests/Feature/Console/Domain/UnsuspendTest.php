<?php

namespace Tests\Feature\Console\Domain;

use App\Domain;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnsuspendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
    }

    protected function tearDown(): void
    {
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
        $code = \Artisan::call("domain:unsuspend unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Domain not found.", $output);

        $domain = $this->getTestDomain('domain-delete.com', [
            'status' => Domain::STATUS_NEW | Domain::STATUS_SUSPENDED,
            'type' => Domain::TYPE_HOSTED,
        ]);

        $this->assertTrue($domain->isSuspended());

        $code = \Artisan::call("domain:unsuspend {$domain->namespace}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $this->assertFalse($domain->fresh()->isSuspended());
    }
}
