<?php

namespace Tests\Feature\Backends;

use App\Backends\PGP;
use App\Backends\Roundcube;
use App\PowerDNS\Domain;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PGPTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->getTestUser('john@kolab.org');
        $user->aliases()->where('alias', 'test-alias@kolab.org')->delete();
        PGP::homedirCleanup($user);
        Domain::where('name', '_woat.kolab.org')->delete();
    }

    protected function tearDown(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->aliases()->where('alias', 'test-alias@kolab.org')->delete();
        PGP::homedirCleanup($user);
        Domain::where('name', '_woat.kolab.org')->delete();

        parent::tearDown();
    }

    /**
     * Test key pair, listing and deletion creation.
     *
     * @group pgp
     * @group roundcube
     */
    public function testKeyCreateListDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');

        $this->assertCount(0, PGP::listKeys($user));

        // Create a key pair
        PGP::keypairCreate($user, $user->email);

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
        $this->assertTrue($userIds[0]->isValid());
        $this->assertFalse($userIds[0]->isRevoked());

        $key = $keys[0]->getPrimaryKey();
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertTrue($key->hasPrivate());
        $this->assertTrue($key->canSign());
        $this->assertFalse($key->canEncrypt());
        $this->assertFalse($key->isRevoked());

        $key = $keys[0]->getSubKeys()[1];
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertFalse($key->canSign());
        $this->assertTrue($key->canEncrypt());
        $this->assertFalse($key->isRevoked());

        // Assert the public key in DNS
        $dns_domain = Domain::where('name', '_woat.kolab.org')->first();
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
        $alias = $user->aliases()->create(['alias' => 'test-alias@kolab.org']);
        PGP::keypairCreate($user, $alias->alias);

        // Assert the created keypair parameters
        $keys = PGP::listKeys($user);
        $this->assertCount(2, $keys);

        $userIds = $keys[1]->getUserIds();
        $this->assertCount(1, $userIds);
        $this->assertSame('test-alias@kolab.org', $userIds[0]->getEmail());
        $this->assertSame('', $userIds[0]->getName());
        $this->assertSame('', $userIds[0]->getComment());
        $this->assertTrue($userIds[0]->isValid());
        $this->assertFalse($userIds[0]->isRevoked());

        $key = $keys[1]->getPrimaryKey();
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertTrue($key->hasPrivate());
        $this->assertTrue($key->canSign());
        $this->assertFalse($key->canEncrypt());
        $this->assertFalse($key->isRevoked());

        $key = $keys[1]->getSubKeys()[1];
        $this->assertSame(\Crypt_GPG_SubKey::ALGORITHM_RSA, $key->getAlgorithm());
        $this->assertSame(0, $key->getExpirationDate());
        $this->assertSame((int) \config('pgp.length'), $key->getLength());
        $this->assertFalse($key->canSign());
        $this->assertTrue($key->canEncrypt());
        $this->assertFalse($key->isRevoked());

        $this->assertSame(2, $dns_domain->records()->where('type', 'TXT')->count());

        // Delete the key
        PGP::keyDelete($user, $user->email);

        $this->assertSame(0, $dns_domain->records()->where('type', 'TXT')->count());
        $this->assertCount(0, PGP::listKeys($user));
    }
}
