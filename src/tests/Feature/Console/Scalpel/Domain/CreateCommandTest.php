<?php

namespace Tests\Feature\Console\Scalpel\Domain;

use App\Domain;
use Tests\TestCase;

class CreateCommandTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestDomain('domain-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command execution
     */
    public function testHandle(): void
    {
        // Test --help argument
        $code = \Artisan::call("scalpel:domain:create --help");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString('--namespace[=NAMESPACE]', $output);
        $this->assertStringContainsString('--type[=TYPE]', $output);
        $this->assertStringContainsString('--status[=STATUS]', $output);
        $this->assertStringContainsString('--tenant_id[=TENANT_ID]', $output);

        $tenant = \App\Tenant::orderBy('id', 'desc')->first();

        // Test successful domain creation
        $code = \Artisan::call(
            "scalpel:domain:create"
            . " --namespace=domain-delete.com"
            . " --type=" . Domain::TYPE_PUBLIC
            . " --tenant_id={$tenant->id}"
        );

        $output = trim(\Artisan::output());

        $domain = $this->getTestDomain('domain-delete.com');

        $this->assertSame(0, $code);
        $this->assertSame($output, (string) $domain->id);
        $this->assertSame('domain-delete.com', $domain->namespace);
        $this->assertSame(Domain::TYPE_PUBLIC, $domain->type);
        $this->assertSame($domain->tenant_id, $tenant->id);
    }
}
