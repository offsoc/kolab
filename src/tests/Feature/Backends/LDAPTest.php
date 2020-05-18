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
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-ldap-test@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user-ldap-test@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test creating/updating/deleting a domain record
     *
     * @group ldap
     */
    public function testDomain(): void
    {
        $this->markTestIncomplete();
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
            'nsroledn' => null,
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
}
