<?php

namespace Tests\Feature\Backends;

use App\Backends\LDAP;
use App\Domain;
use App\Entitlement;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LDAPTest extends TestCase
{
    private $ldap_config = [];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->ldap_config = [
            'ldap.hosts' => \config('ldap.hosts'),
        ];

        $this->deleteTestUser('user-ldap-test@' . \config('app.domain'));
        $this->deleteTestDomain('testldap.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \config($this->ldap_config);

        $this->deleteTestUser('user-ldap-test@' . \config('app.domain'));
        $this->deleteTestDomain('testldap.com');

        parent::tearDown();
    }

    /**
     * Test handling connection errors
     *
     * @group ldap
     */
    public function testConnectException(): void
    {
        \config(['ldap.hosts' => 'non-existing.host']);

        $this->expectException(\Exception::class);

        LDAP::connect();
    }

    /**
     * Test creating/updating/deleting a domain record
     *
     * @group ldap
     */
    public function testDomain(): void
    {
        Queue::fake();

        $domain = $this->getTestDomain('testldap.com', [
                'type' => Domain::TYPE_EXTERNAL,
                'status' => Domain::STATUS_NEW | Domain::STATUS_ACTIVE,
        ]);

        // Create the domain
        LDAP::createDomain($domain);

        $ldap_domain = LDAP::getDomain($domain->namespace);

        $expected = [
            'associateddomain' => $domain->namespace,
            'inetdomainstatus' => $domain->status,
            'objectclass' => [
                'top',
                'domainrelatedobject',
                'inetdomain'
            ],
        ];

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_domain[$attr]) ? $ldap_domain[$attr] : null);
        }

        // TODO: Test other attributes, aci, roles/ous

        // Update the domain
        $domain->status |= User::STATUS_LDAP_READY;

        LDAP::updateDomain($domain);

        $expected['inetdomainstatus'] = $domain->status;

        $ldap_domain = LDAP::getDomain($domain->namespace);

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_domain[$attr]) ? $ldap_domain[$attr] : null);
        }

        // Delete the domain
        LDAP::deleteDomain($domain);

        $this->assertSame(null, LDAP::getDomain($domain->namespace));
    }

    /**
     * Test creating/editing/deleting a user record
     *
     * @group ldap
     */
    public function testUser(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-ldap-test@' . \config('app.domain'));

        LDAP::createUser($user);

        $ldap_user = LDAP::getUser($user->email);

        $expected = [
            'objectclass' => [
                'top',
                'inetorgperson',
                'inetuser',
                'kolabinetorgperson',
                'mailrecipient',
                'person',
                'organizationalPerson',
            ],
            'mail' => $user->email,
            'uid' => $user->email,
            'nsroledn' => [
                'cn=imap-user,' . \config('ldap.hosted.root_dn')
            ],
            'cn' => 'unknown',
            'displayname' => '',
            'givenname' => '',
            'sn' => 'unknown',
            'inetuserstatus' => $user->status,
            'mailquota' => null,
            'o' => '',
            'alias' => null,
        ];

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_user[$attr]) ? $ldap_user[$attr] : null);
        }

        // Add aliases, and change some user settings, and entitlements
        $user->setSettings([
            'first_name' => 'Firstname',
            'last_name' => 'Lastname',
            'organization' => 'Org',
            'country' => 'PL',
        ]);
        $user->status |= User::STATUS_IMAP_READY;
        $user->save();
        $aliases = ['t1-' . $user->email, 't2-' . $user->email];
        $user->setAliases($aliases);
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $user->assignPackage($package_kolab);

        LDAP::updateUser($user->fresh());

        $expected['alias'] = $aliases;
        $expected['o'] = 'Org';
        $expected['displayname'] = 'Lastname, Firstname';
        $expected['givenname'] = 'Firstname';
        $expected['cn'] = 'Firstname Lastname';
        $expected['sn'] = 'Lastname';
        $expected['inetuserstatus'] = $user->status;
        $expected['mailquota'] = 2097152;
        $expected['nsroledn'] = null;

        $ldap_user = LDAP::getUser($user->email);

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_user[$attr]) ? $ldap_user[$attr] : null);
        }

        // Update entitlements
        $sku_activesync = \App\Sku::where('title', 'activesync')->first();
        $sku_groupware = \App\Sku::where('title', 'groupware')->first();
        $user->assignSku($sku_activesync, 1);
        Entitlement::where(['sku_id' => $sku_groupware->id, 'entitleable_id' => $user->id])->delete();

        LDAP::updateUser($user->fresh());

        $expected_roles = [
            'activesync-user',
            'imap-user'
        ];

        $ldap_user = LDAP::getUser($user->email);

        $this->assertCount(2, $ldap_user['nsroledn']);

        $ldap_roles = array_map(
            function ($role) {
                if (preg_match('/^cn=([a-z0-9-]+)/', $role, $m)) {
                    return $m[1];
                } else {
                    return $role;
                }
            },
            $ldap_user['nsroledn']
        );

        $this->assertSame($expected_roles, $ldap_roles);

        // Delete the user
        LDAP::deleteUser($user);

        $this->assertSame(null, LDAP::getUser($user->email));
    }

    /**
     * Test handling errors on user creation
     *
     * @group ldap
     */
    public function testCreateUserException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Failed to create user/');

        $user = new User([
                'email' => 'test-non-existing-ldap@non-existing.org',
                'status' => User::STATUS_ACTIVE,
        ]);

        LDAP::createUser($user);
    }

    /**
     * Test handling update of a non-existing domain
     *
     * @group ldap
     */
    public function testUpdateDomainException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/domain not found/');

        $domain = new Domain([
                'namespace' => 'testldap.com',
                'type' => Domain::TYPE_EXTERNAL,
                'status' => Domain::STATUS_NEW | Domain::STATUS_ACTIVE,
        ]);

        LDAP::updateDomain($domain);
    }

    /**
     * Test handling update of a non-existing user
     *
     * @group ldap
     */
    public function testUpdateUserException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/user not found/');

        $user = new User([
                'email' => 'test-non-existing-ldap@kolab.org',
                'status' => User::STATUS_ACTIVE,
        ]);

        LDAP::updateUser($user);
    }
}
