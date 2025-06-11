<?php

namespace Tests\Feature\Console\Status;

use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\Support\Facades\Roundcube;
use App\Support\Facades\Storage;
use Tests\TestCase;
use App\Utils;

class HealthTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        \config(['app.with_ldap' => true]);
        \config(['app.with_imap' => true]);

        $userPassword = Utils::generatePassphrase();
        $user = $this->getTestUser('user@health-test.com', ['password' => $userPassword]);

        DAV::shouldReceive('healthcheck')->once()->andReturn(true);
        IMAP::shouldReceive('healthcheck')->once()->andReturn(true);
        LDAP::shouldReceive('healthcheck')->once()->andReturn(true);
        Roundcube::shouldReceive('healthcheck')->once()->andReturn(true);
        Storage::shouldReceive('healthcheck')->once()->andReturn(true);

        $code = \Artisan::call("status:health --check DB --check Redis --check Roundcube --check DAV --check IMAP --check LDAP --check Storage --user {$user->email} --password $userPassword");
        $output = trim(\Artisan::output());
        $this->assertStringNotContainsString("Error", $output);
        $this->assertSame(0, $code);
    }
}
