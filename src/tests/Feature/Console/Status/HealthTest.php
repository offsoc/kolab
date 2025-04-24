<?php

namespace Tests\Feature\Console\Status;

use Tests\TestCase;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\Support\Facades\Roundcube;
use App\Support\Facades\Storage;

class HealthTest extends TestCase
{
    /**
     * Test the command
     *
     * @group meet
     * @group mollie
     */
    public function testHandle(): void
    {
        \config(['app.with_ldap' => true]);
        \config(['app.with_imap' => true]);

        DAV::shouldReceive('healthcheck')->once()->andReturn(true);
        IMAP::shouldReceive('healthcheck')->once()->andReturn(true);
        LDAP::shouldReceive('healthcheck')->once()->andReturn(true);
        Roundcube::shouldReceive('healthcheck')->once()->andReturn(true);
        Storage::shouldReceive('healthcheck')->once()->andReturn(true);

        $code = \Artisan::call("status:health");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
    }
}
