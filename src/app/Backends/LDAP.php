<?php

namespace App\Backend;

use App\Domain;
use App\User;
use Illuminate\Support\Facades\Config;

class LDAP
{
    // Get settings with Config::get('ldap.$mode.$key');
    // or the less unambiguous config('ldap.$mode.$key')l

    /**
     * Create a domain in LDAP.
     *
     * @param Domain $domain The domain to create.
     *
     * @return void
     */
    public static function createDomain($domain)
    {
        $config = self::getConfig('admin');

        $ldap = new \Net_LDAP3($config);

        $ldap->connect();

        $ldap->bind(
            //config('ldap.admin.bind_dn'),
            "cn=Directory Manager",
            //config('ldap.admin.bind_pw')
            "Welcome2KolabSystems"
        );

        //$hosted_root_dn = config('ldap.hosted.root_dn');
        $hosted_root_dn = "dc=hosted,dc=com";
        $mgmt_root_dn = "dc=mgmt,dc=com";

        $domain_base_dn = "ou={$domain->namespace},{$hosted_root_dn}";

        $aci = [
            '(targetattr = "*")'
            . '(version 3.0; acl "Deny Unauthorized"; deny (all)'
            . '(userdn != "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn
            . ' || ldap:///ou=People,' . $domain_base_dn . '??sub?(objectclass=inetorgperson)") '
            . 'AND NOT roledn = "ldap:///cn=kolab-admin,' . $mgmt_root_dn . '";)',

            '(targetattr != "userPassword")'
            . '(version 3.0;acl "Search Access";allow (read,compare,search)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn
            . ' || ldap:///ou=People,' . $domain_base_dn . '??sub?(objectclass=inetorgperson)");)',

            '(targetattr = "*")'
            . '(version 3.0;acl "Kolab Administrators";allow (all)'
            . '(roledn = "ldap:///cn=kolab-admin,' . $domain_base_dn
            . ' || ldap:///cn=kolab-admin,' . $mgmt_root_dn . '");)'
        ];

        $entry = [
            'aci' => $aci,
            'associateddomain' => $domain->namespace,
            'inetdomainbasedn' => $domain_base_dn,
            'objectclass' => [
                'top',
                'domainrelatedobject',
                'inetdomain'
            ],
        ];

        $dn = "associateddomain={$domain->namespace},{$config['domain_base_dn']}";

        if (!$ldap->get_entry($dn)) {
            $ldap->add_entry($dn, $entry);
        }

        // create ou, roles, ous
        $entry = [
            'description'   => $domain->namespace,
            'objectclass' => [
                'top',
                'organizationalunit'
            ],
            'ou'            => $domain->namespace,
        ];

        $entry['aci'] = array(
            '(targetattr = "*")'
            . '(version 3.0;acl "Deny Unauthorized"; deny (all)'
            . '(userdn != "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn
            . ' || ldap:///ou=People,' . $domain_base_dn . '??sub?(objectclass=inetorgperson)") '
            . 'AND NOT roledn = "ldap:///cn=kolab-admin,' . $mgmt_root_dn . '";)',

            '(targetattr != "userPassword")'
            . '(version 3.0;acl "Search Access";allow (read,compare,search,write)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn
            . ' || ldap:///ou=People,' . $domain_base_dn . '??sub?(objectclass=inetorgperson)");)',

            '(targetattr = "*")'
            . '(version 3.0;acl "Kolab Administrators";allow (all)'
            . '(roledn = "ldap:///cn=kolab-admin,' . $domain_base_dn
            . ' || ldap:///cn=kolab-admin,' . $mgmt_root_dn . '");)',

            '(target = "ldap:///ou=*,' . $domain_base_dn . '")'
            . '(targetattr="objectclass || aci || ou")'
            . '(version 3.0;acl "Allow Domain sub-OU Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn . '");)',

            '(target = "ldap:///uid=*,ou=People,' . $domain_base_dn . '")(targetattr="*")'
            . '(version 3.0;acl "Allow Domain First User Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn . '");)',

            '(target = "ldap:///cn=*,' . $domain_base_dn . '")(targetattr="objectclass || cn")'
            . '(version 3.0;acl "Allow Domain Role Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmt_root_dn . '");)',
        );

        if (!$ldap->get_entry($domain_base_dn)) {
            $ldap->add_entry($domain_base_dn, $entry);
        }

        foreach (['Groups', 'People', 'Resources', 'Shared Folders'] as $item) {
            if (!$ldap->get_entry("ou={$item},{$domain_base_dn}")) {
                $ldap->add_entry(
                    "ou={$item},{$domain_base_dn}",
                    [
                        'ou' => $item,
                        'description' => $item,
                        'objectclass' => [
                            'top',
                            'organizationalunit'
                        ]
                    ]
                );
            }
        }

        foreach (['kolab-admin', 'imap-user', 'activesync-user', 'billing-user'] as $item) {
            if (!$ldap->get_entry("cn={$item},{$domain_base_dn}")) {
                $ldap->add_entry(
                    "cn={$item},{$domain_base_dn}",
                    [
                        'cn' => $item,
                        'description' => "{$item} role",
                        'objectclass' => [
                            'top',
                            'ldapsubentry',
                            'nsmanagedroledefinition',
                            'nsroledefinition',
                            'nssimpleroledefinition'
                        ]
                    ]
                );
            }
        }

        $ldap->close();
    }

    /**
     * Create a user in LDAP.
     *
     * Only need to add user if in any of the local domains? Figure that out here for now. Should
     * have Context-Based Access Controls before the job is queued though, probably.
     *
     * Use one of three modes;
     *
     * 1) The authenticated user account.
     *
     *    * Only valid if the authenticated user is a domain admin.
     *    * We don't know the originating user here.
     *    * We certainly don't have its password anymore.
     *
     * 2) The hosted kolab account.
     *
     * 3) The Directory Manager account.
     *
     * @param User $user The user account to create.
     *
     * @return void
     */
    public static function createUser($user)
    {
        $config = self::getConfig('admin');

        $ldap = new \Net_LDAP3($config);

        $ldap->connect();

        $ldap->bind(
            //config('ldap.admin.bind_dn'),
            "cn=Directory Manager",
            //config('ldap.admin.bind_pw')
            "Welcome2KolabSystems"
        );

        $firstName = $user->getSetting('first_name');
        $lastName = $user->getSetting('last_name');

        $cn = "unknown";
        $displayname = "";

        if ($firstName) {
            if ($lastName) {
                $cn = "{$firstName} {$lastName}";
                $displayname = "{$lastName}, {$firstName}";
            } else {
                $lastName = "unknown";
                $cn = "{$firstName}";
                $displayname = "{$firstName}";
            }
        } else {
            $firstName = "";
            if ($lastName) {
                $cn = "{$lastName}";
                $displayname = "{$lastName}";
            } else {
                $lastName = "unknown";
            }
        }

        $entry = [
            'cn' => $cn,
            'displayname' => $displayname,
            'givenname' => $firstName,
            'objectclass' => [
                'top',
                'inetorgperson',
                'kolabinetorgperson',
                'mailrecipient',
                'person'
            ],
            'mail' => $user->email,
            'sn' => $lastName,
            'uid' => $user->email,
            'userpassword' => $user->password_ldap,
        ];

        list($_local, $_domain) = explode('@', $user->email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return false;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "uid={$user->email},ou=People,{$base_dn}";

        if (!$ldap->get_entry($dn)) {
            $ldap->add_entry($dn, $entry);
        }

        $ldap->close();
    }

    /**
     * Update a domain in LDAP.
     *
     * @param \App\Domain $domain The domain to update.
     *
     * @return void
     */
    public static function updateDomain($domain)
    {
        //
    }

    /**
     * Update a user in LDAP.
     *
     * @param \App\User $user The user account to update.
     *
     * @return void
     */
    public static function updateUser($user)
    {
        $config = self::getConfig('admin');

        $ldap = new \Net_LDAP3($config);

        $ldap->connect();

        $ldap->bind(
            //config('ldap.admin.bind_dn'),
            "cn=Directory Manager",
            //config('ldap.admin.bind_pw')
            "Welcome2KolabSystems"
        );

        list($_local, $_domain) = explode('@', $user->email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return false;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "uid={$user->email},ou=People,{$base_dn}";

        $old_entry = $ldap->get_entry($dn);

        $new_entry = $old_entry;

        $firstName = $user->getSetting('first_name');
        $lastName = $user->getSetting('last_name');

        $cn = "unknown";
        $displayname = "";

        if ($firstName) {
            if ($lastName) {
                $cn = "{$firstName} {$lastName}";
                $displayname = "{$lastName}, {$firstName}";
            } else {
                $lastName = "unknown";
                $cn = "{$firstName}";
                $displayname = "{$firstName}";
            }
        } else {
            $firstName = "";
            if ($lastName) {
                $cn = "{$lastName}";
                $displayname = "{$lastName}";
            } else {
                $lastName = "unknown";
            }
        }

        $new_entry['cn'] = $cn;
        $new_entry['displayname'] = $displayname;
        $new_entry['givenname'] = $firstName;
        $new_entry['sn'] = $lastName;
        $new_entry['userpassword'] = $user->password_ldap;

        $ldap->modify_entry($dn, $old_entry, $new_entry);

        $ldap->close();
    }

    private static function getConfig($privilege)
    {
        $config = [
            'domain_base_dn' => config('ldap.domain_base_dn'),
            'domain_filter' => config('ldap.domain_filter'),
            'domain_name_attribute' => config('ldap.domain_name_attribute'),
            'hosts' => config('ldap.hosts'),
            'sort' => false,
            'vlv' => false
        ];

        return $config;
    }
}
