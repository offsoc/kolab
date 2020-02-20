<?php

namespace App\Backends;

use App\Domain;
use App\User;

class LDAP
{
    /**
     * Create a domain in LDAP.
     *
     * @param \App\Domain $domain The domain to create.
     *
     * @return void
     */
    public static function createDomain(Domain $domain)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $hosted_root_dn = \config('ldap.hosted.root_dn');
        $mgmt_root_dn = \config('ldap.admin.root_dn');

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
            'description' => $domain->namespace,
            'objectclass' => [
                'top',
                'organizationalunit'
            ],
            'ou' => $domain->namespace,
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
     * @param \App\User $user The user account to create.
     *
     * @return bool|void
     */
    public static function createUser(User $user)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        list($_local, $_domain) = explode('@', $user->email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return false;
        }

        $entry = [
            'objectclass' => [
                'top',
                'inetorgperson',
                'kolabinetorgperson',
                'mailrecipient',
                'person'
            ],
            'mail' => $user->email,
            'uid' => $user->email,
        ];

        self::setUserAttributes($user, $entry);

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

    public static function deleteDomain($domain)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $hosted_root_dn = \config('ldap.hosted.root_dn');
        $mgmt_root_dn = \config('ldap.admin.root_dn');

        $domain_base_dn = "ou={$domain->namespace},{$hosted_root_dn}";

        if ($ldap->get_entry($domain_base_dn)) {
            $ldap->delete_entry_recursive($domain_base_dn);
        }

        if ($ldap_domain = $ldap->find_domain($domain->namespace)) {
            if ($ldap->get_entry($ldap_domain['dn'])) {
                $ldap->delete_entry($ldap_domain['dn']);
            }
        }
    }

    public static function deleteUser($user)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        list($_local, $_domain) = explode('@', $user->email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return false;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "uid={$user->email},ou=People,{$base_dn}";

        if (!$ldap->get_entry($dn)) {
            return false;
        }

        $ldap->delete_entry($dn);
    }

    /**
     * Update a user in LDAP.
     *
     * @param \App\User $user The user account to update.
     *
     * @return bool|void
     */
    public static function updateUser(User $user)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        list($_local, $_domain) = explode('@', $user->email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return false;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "uid={$user->email},ou=People,{$base_dn}";

        $old_entry = $ldap->get_entry($dn);
        $new_entry = $old_entry;

        self::setUserAttributes($user, $new_entry);

        $ldap->modify_entry($dn, $old_entry, $new_entry);

        $ldap->close();
    }

    /**
     * Initialize connection to LDAP
     */
    private static function initLDAP(array $config, string $privilege = 'admin')
    {
        $ldap = new \Net_LDAP3($config);

        $ldap->connect();

        $ldap->bind(\config("ldap.{$privilege}.bind_dn"), \config("ldap.{$privilege}.bind_pw"));

        // TODO: error handling

        return $ldap;
    }

    /**
     * Set common user attributes
     */
    private static function setUserAttributes(User $user, array &$entry)
    {
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

        $entry['cn'] = $cn;
        $entry['displayname'] = $displayname;
        $entry['givenname'] = $firstName;
        $entry['sn'] = $lastName;
        $entry['userpassword'] = $user->password_ldap;
    }

    /**
     * Get LDAP configuration for specified access level
     */
    private static function getConfig(string $privilege)
    {
        $config = [
            'domain_base_dn' => \config('ldap.domain_base_dn'),
            'domain_filter' => \config('ldap.domain_filter'),
            'domain_name_attribute' => \config('ldap.domain_name_attribute'),
            'hosts' => \config('ldap.hosts'),
            'sort' => false,
            'vlv' => false,
            'log_hook' => 'App\Backends\LDAP::logHook',
        ];

        return $config;
    }

    /**
     * Logging callback
     */
    public static function logHook($level, $msg): void
    {
        if (
            ($level == LOG_INFO || $level == LOG_DEBUG || $level == LOG_NOTICE)
            && !\config('app.debug')
        ) {
            return;
        }

        switch ($level) {
            case LOG_CRIT:
                $function = 'critical';
                break;
            case LOG_EMERG:
                $function = 'emergency';
                break;
            case LOG_ERR:
                $function = 'error';
                break;
            case LOG_ALERT:
                $function = 'alert';
                break;
            case LOG_WARNING:
                $function = 'warning';
                break;
            case LOG_INFO:
                $function = 'info';
                break;
            case LOG_DEBUG:
                $function = 'debug';
                break;
            case LOG_NOTICE:
                $function = 'notice';
                break;
            default:
                $function = 'info';
        }

        if (is_array($msg)) {
            $msg = implode("\n", $msg);
        }

        $msg = '[LDAP] ' . $msg;

        \Log::{$function}($msg);
    }
}
