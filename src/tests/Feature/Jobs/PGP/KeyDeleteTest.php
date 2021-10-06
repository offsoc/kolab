<?php

namespace Tests\Feature\Jobs\PGP;

use App\Backends\PGP;
use App\Backends\Roundcube;
use App\User;
use App\UserAlias;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KeyDeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $user = $this->getTestUser('john@kolab.org');
        UserAlias::where('alias', 'test-alias@kolab.org')->delete();
        PGP::homedirCleanup($user);
        \App\PowerDNS\Domain::where('name', '_woat.kolab.org')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        UserAlias::where('alias', 'test-alias@kolab.org')->delete();
        PGP::homedirCleanup($user);
        \App\PowerDNS\Domain::where('name', '_woat.kolab.org')->delete();

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group pgp
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');

        // First run the key create job
        $job = new \App\Jobs\PGP\KeyCreateJob($user->id, $user->email);
        $job->handle();

        // Assert the public key in DNS exist at this point
        $dns_domain = \App\PowerDNS\Domain::where('name', '_woat.kolab.org')->first();
        $this->assertNotNull($dns_domain);
        $this->assertSame(1, $dns_domain->records()->where('type', 'TXT')->count());

        // Run the job
        $job = new \App\Jobs\PGP\KeyDeleteJob($user->id, $user->email);
        $job->handle();

        $this->assertSame(0, $dns_domain->records()->where('type', 'TXT')->count());

        // Assert the created keypair parameters
        $keys = PGP::listKeys($user);

        $this->assertCount(0, $keys);
    }
}
