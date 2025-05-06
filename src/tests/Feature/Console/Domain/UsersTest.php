<?php

namespace Tests\Feature\Console\Domain;

use App\User;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /**
     * Test the command
     */
    public function testHandle(): void
    {
        // Existing domain
        $code = \Artisan::call("domain:users kolab.org");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $john = User::where('email', 'john@kolab.org')->first();

        $this->assertTrue(str_contains($output, (string) $john->id));

        // TODO: Test output format and additional attributes
    }
}
