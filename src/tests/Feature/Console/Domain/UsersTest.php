<?php

namespace Tests\Feature\Console\Domain;

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

        $john = \App\User::where('email', 'john@kolab.org')->first();

        $this->assertTrue(strpos($output, (string) $john->id) !== false);

        // TODO: Test output format and additional attributes
    }
}
