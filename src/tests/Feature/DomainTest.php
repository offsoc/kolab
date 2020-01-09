<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DomainTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $statuses = [ "new", "active", "confirmed", "suspended", "deleted" ];

        $domains = \App\Utils::powerSet($statuses);

        foreach ($domains as $namespace_elements) {
            $namespace = implode('-', $namespace_elements) . '.com';

            $domain = Domain::where('namespace', $namespace)->first();
            if ($domain) {
                $domain->delete();
            }
        }
    }

    public function testDomainStatus()
    {
        $statuses = [ "new", "active", "confirmed", "suspended", "deleted" ];

        $domains = \App\Utils::powerSet($statuses);

        foreach ($domains as $namespace_elements) {
            $namespace = implode('-', $namespace_elements) . '.com';

            $status = 1;

            if (in_array("new", $namespace_elements)) {
                $status += Domain::STATUS_NEW;
            }

            if (in_array("active", $namespace_elements)) {
                $status += Domain::STATUS_ACTIVE;
            }

            if (in_array("confirmed", $namespace_elements)) {
                $status += Domain::STATUS_CONFIRMED;
            }

            if (in_array("suspended", $namespace_elements)) {
                $status += Domain::STATUS_SUSPENDED;
            }

            if (in_array("deleted", $namespace_elements)) {
                $status += Domain::STATUS_DELETED;
            }

            $domain = Domain::firstOrCreate(
                [
                    'namespace' => $namespace,
                    'status' => $status,
                    'type' => Domain::TYPE_EXTERNAL
                ]
            );

            $this->assertTrue($domain->status > 1);
        }
    }
}
