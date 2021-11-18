<?php

namespace Tests\Feature\Backends;

use App\Backends\LDAP;
use App\Domain;
use App\Group;
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
        $this->deleteTestGroup('group@kolab.org');
        // TODO: Remove group members
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \config($this->ldap_config);

        $this->deleteTestUser('user-ldap-test@' . \config('app.domain'));
        $this->deleteTestDomain('testldap.com');
        $this->deleteTestGroup('group@kolab.org');
        // TODO: Remove group members

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
     * Test creating/updating/deleting a group record
     *
     * @group ldap
     */
    public function testGroup(): void
    {
        Queue::fake();

        $root_dn = \config('ldap.hosted.root_dn');
        $group = $this->getTestGroup('group@kolab.org', [
                'members' => ['member1@testldap.com', 'member2@testldap.com']
        ]);
        $group->setSetting('sender_policy', '["test.com"]');

        // Create the group
        LDAP::createGroup($group);

        $ldap_group = LDAP::getGroup($group->email);

        $expected = [
            'cn' => 'group',
            'dn' => 'cn=group,ou=Groups,ou=kolab.org,' . $root_dn,
            'mail' => $group->email,
            'objectclass' => [
                'top',
                'groupofuniquenames',
                'kolabgroupofuniquenames'
            ],
            'kolaballowsmtpsender' => 'test.com',
            'uniquemember' => [
                'uid=member1@testldap.com,ou=People,ou=kolab.org,' . $root_dn,
                'uid=member2@testldap.com,ou=People,ou=kolab.org,' . $root_dn,
            ],
        ];

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_group[$attr]) ? $ldap_group[$attr] : null, "Group $attr attribute");
        }

        // Update members
        $group->members = ['member3@testldap.com'];
        $group->save();
        $group->setSetting('sender_policy', '["test.com","-"]');

        LDAP::updateGroup($group);

        // TODO: Should we force this to be always an array?
        $expected['uniquemember'] = 'uid=member3@testldap.com,ou=People,ou=kolab.org,' . $root_dn;
        $expected['kolaballowsmtpsender'] = ['test.com', '-'];

        $ldap_group = LDAP::getGroup($group->email);

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_group[$attr]) ? $ldap_group[$attr] : null, "Group $attr attribute");
        }

        $this->assertSame(['member3@testldap.com'], $group->fresh()->members);

        // Update members (add non-existing local member, expect it to be aot-removed from the group)
        // Update group name and sender_policy
        $group->members = ['member3@testldap.com', 'member-local@kolab.org'];
        $group->name = 'Te(=ść)1';
        $group->save();
        $group->setSetting('sender_policy', null);

        LDAP::updateGroup($group);

        // TODO: Should we force this to be always an array?
        $expected['uniquemember'] = 'uid=member3@testldap.com,ou=People,ou=kolab.org,' . $root_dn;
        $expected['kolaballowsmtpsender'] = null;
        $expected['dn'] = 'cn=Te(\\3dść)1,ou=Groups,ou=kolab.org,' . $root_dn;
        $expected['cn'] = 'Te(=ść)1';

        $ldap_group = LDAP::getGroup($group->email);

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_group[$attr]) ? $ldap_group[$attr] : null, "Group $attr attribute");
        }

        $this->assertSame(['member3@testldap.com'], $group->fresh()->members);

        // We called save() twice, and setSettings() three times,
        // this is making sure that there's no job executed by the LDAP backend
        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 5);

        // Delete the domain
        LDAP::deleteGroup($group);

        $this->assertSame(null, LDAP::getGroup($group->email));
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
        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $user->assignPackage($package_kolab);

        LDAP::updateUser($user->fresh());

        $expected['alias'] = $aliases;
        $expected['o'] = 'Org';
        $expected['displayname'] = 'Lastname, Firstname';
        $expected['givenname'] = 'Firstname';
        $expected['cn'] = 'Firstname Lastname';
        $expected['sn'] = 'Lastname';
        $expected['inetuserstatus'] = $user->status;
        $expected['mailquota'] = 5242880;
        $expected['nsroledn'] = null;

        $ldap_user = LDAP::getUser($user->email);

        foreach ($expected as $attr => $value) {
            $this->assertEquals($value, isset($ldap_user[$attr]) ? $ldap_user[$attr] : null);
        }

        // Update entitlements
        $sku_activesync = \App\Sku::withEnvTenantContext()->where('title', 'activesync')->first();
        $sku_groupware = \App\Sku::withEnvTenantContext()->where('title', 'groupware')->first();
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
     * Test handling errors on a group creation
     *
     * @group ldap
     */
    public function testCreateGroupException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Failed to create group/');

        $group = new Group([
                'name' => 'test',
                'email' => 'test@testldap.com',
                'status' => Group::STATUS_NEW | Group::STATUS_ACTIVE,
        ]);

        LDAP::createGroup($group);
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
     * Test handling update of a non-existing group
     *
     * @group ldap
     */
    public function testUpdateGroupException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/group not found/');

        $group = new Group([
                'name' => 'test',
                'email' => 'test@testldap.com',
                'status' => Group::STATUS_NEW | Group::STATUS_ACTIVE,
        ]);

        LDAP::updateGroup($group);
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
