<?php

namespace Tests\Feature\Jobs\PGP;

use App\Backends\PGP;
use App\Backends\Roundcube;
use App\User;
use App\UserAlias;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KeyCreateTest extends TestCase
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
        $user = $this->getTestUser('john@kolab.org');

        $job = new \App\Jobs\PGP\KeyCreateJob($user->id, $user->email);
        $job->handle();

        // Assert the Enigma storage has been initialized and contains the key
        $files = Roundcube::enigmaList($user->email);
        // TODO: More detailed asserts on the filestore content, but it's specific to GPG version
        $this->assertTrue(count($files) > 1);

        // Assert the created keypair parameters
        $keys = PGP::listKeys($user);

        $this->assertCount(1, $keys);

        $userIds = $keys[0]->getUserIds();
        $this->assertCount(1, $userIds);
        $this->assertSame($user->email, $userIds[0]->getEmail());
        $this->assertSame('', $userIds[0]->getName());
        $this->assertSame('', $userIds[0]->getComment());
        $this->assertSame(true, $userIds[0]->isValid());
        $this->assertSame(false, $userIds[0]->isRevoked());

        $key = $keys[0]->getPrimaryKey();
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertSame(true, $key->hasPrivate());
        $this->assertSame(true, $key->canSign());
        $this->assertSame(false, $key->canEncrypt());
        $this->assertSame(false, $key->isRevoked());

        $key = $keys[0]->getSubKeys()[1];
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertSame(false, $key->canSign());
        $this->assertSame(true, $key->canEncrypt());
        $this->assertSame(false, $key->isRevoked());

        // Assert the public key in DNS
        $dns_domain = \App\PowerDNS\Domain::where('name', '_woat.kolab.org')->first();
        $this->assertNotNull($dns_domain);
        $dns_record = $dns_domain->records()->where('type', 'TXT')->first();
        $this->assertNotNull($dns_record);
        $this->assertSame('TXT', $dns_record->type);
        $this->assertSame(sha1('john') . '._woat.kolab.org', $dns_record->name);
        $this->assertMatchesRegularExpression(
            '/^v=woat1,public_key='
                . '-----BEGIN PGP PUBLIC KEY BLOCK-----'
                . '[a-zA-Z0-9\n\/+=]+'
                . '-----END PGP PUBLIC KEY BLOCK-----'
                . '$/',
            $dns_record->content
        );

        // Test an alias
        Queue::fake();
        UserAlias::create(['user_id' => $user->id, 'alias' => 'test-alias@kolab.org']);
        $job = new \App\Jobs\PGP\KeyCreateJob($user->id, 'test-alias@kolab.org');
        $job->handle();

        // Assert the created keypair parameters
        $keys = PGP::listKeys($user);

        $this->assertCount(2, $keys);

        $userIds = $keys[1]->getUserIds();
        $this->assertCount(1, $userIds);
        $this->assertSame('test-alias@kolab.org', $userIds[0]->getEmail());
        $this->assertSame('', $userIds[0]->getName());
        $this->assertSame('', $userIds[0]->getComment());
        $this->assertSame(true, $userIds[0]->isValid());
        $this->assertSame(false, $userIds[0]->isRevoked());

        $key = $keys[1]->getPrimaryKey();
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertSame(true, $key->hasPrivate());
        $this->assertSame(true, $key->canSign());
        $this->assertSame(false, $key->canEncrypt());
        $this->assertSame(false, $key->isRevoked());

        $key = $keys[1]->getSubKeys()[1];
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertSame(false, $key->canSign());
        $this->assertSame(true, $key->canEncrypt());
        $this->assertSame(false, $key->isRevoked());

        $this->assertSame(2, $dns_domain->records()->where('type', 'TXT')->count());
    }
}
