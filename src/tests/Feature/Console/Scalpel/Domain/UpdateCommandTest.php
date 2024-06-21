<?php

namespace Tests\Feature\Console\Scalpel\Domain;

use App\Domain;
use Tests\TestCase;

class UpdateCommandTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestDomain('domain-delete.com');
        $this->deleteTestDomain('domain-delete-mod.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestDomain('domain-delete.com');
        $this->deleteTestDomain('domain-delete-mod.com');

        parent::tearDown();
    }

    /**
     * Test the command execution
     */
    public function testHandle(): void
    {
        // Test unknown domain
        $this->artisan("scalpel:domain:update unknown")
             ->assertExitCode(1)
             ->expectsOutput("No such domain unknown");

        $domain = $this->getTestDomain('domain-delete.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);

        // Test successful update
        $this->artisan("scalpel:domain:update {$domain->id}"
            . " --namespace=domain-delete-mod.com --type=" . Domain::TYPE_PUBLIC)
             ->assertExitCode(0);

        $domain->refresh();

        $this->assertSame('domain-delete-mod.com', $domain->namespace);
        $this->assertSame(Domain::TYPE_PUBLIC, $domain->type);

        // Test --help argument
        $code = \Artisan::call("scalpel:domain:update --help");
        $output = trim(\Artisan::output());

        $this->assertStringContainsString('--with-deleted', $output);
        $this->assertStringContainsString('--namespace[=NAMESPACE]', $output);
        $this->assertStringContainsString('--type[=TYPE]', $output);
        $this->assertStringContainsString('--status[=STATUS]', $output);
        $this->assertStringContainsString('--tenant_id[=TENANT_ID]', $output);
        $this->assertStringContainsString('--created_at[=CREATED_AT]', $output);
        $this->assertStringContainsString('--updated_at[=UPDATED_AT]', $output);
        $this->assertStringContainsString('--deleted_at[=DELETED_AT]', $output);
        $this->assertStringNotContainsString('--id', $output);
    }
}
