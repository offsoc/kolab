<?php

namespace App\Backends;

use App\Domain;
use App\User;

class LDAP
{
    /** @const array UserSettings used by the backend */
    public const USER_SETTINGS = [
        'first_name',
        'last_name',
        'organization',
    ];

    /** @var ?\Net_LDAP3 LDAP connection object */
    protected static $ldap;


    /**
     * Starts a new LDAP connection that will be used by all methods
     * until you call self::disconnect() explicitely. Normally every
     * method uses a separate connection.
     *
     * @throws \Exception
     */
    public static function connect(): void
    {
        if (empty(self::$ldap)) {
            $config = self::getConfig('admin');
            self::$ldap = self::initLDAP($config);
        }
    }

    /**
     * Close the connection created by self::connect()
     */
    public static function disconnect(): void
    {
        if (!empty(self::$ldap)) {
            self::$ldap->close();
            self::$ldap = null;
        }
    }

    /**
     * Create a domain in LDAP.
     *
     * @param \App\Domain $domain The domain to create.
     *
     * @throws \Exception
     */
    public static function createDomain(Domain $domain): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $hostedRootDN = \config('ldap.hosted.root_dn');
        $mgmtRootDN = \config('ldap.admin.root_dn');

        $result = $ldap->search($hostedRootDN, "(associateddomain={$domain->namespace})", "sub");

        if ($result && $result->count() > 0) {
            return;
        }

        $domainBaseDN = "ou={$domain->namespace},{$hostedRootDN}";

        $aci = [
            '(targetattr = "*")'
            . '(version 3.0; acl "Deny Unauthorized"; deny (all)'
            . '(userdn != "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN
            . ' || ldap:///ou=People,' . $domainBaseDN . '??sub?(objectclass=inetorgperson)") '
            . 'AND NOT roledn = "ldap:///cn=kolab-admin,' . $mgmtRootDN . '";)',

            '(targetattr != "userPassword")'
            . '(version 3.0;acl "Search Access";allow (read,compare,search)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN
            . ' || ldap:///ou=People,' . $domainBaseDN . '??sub?(objectclass=inetorgperson)");)',

            '(targetattr = "*")'
            . '(version 3.0;acl "Kolab Administrators";allow (all)'
            . '(roledn = "ldap:///cn=kolab-admin,' . $domainBaseDN
            . ' || ldap:///cn=kolab-admin,' . $mgmtRootDN . '");)'
        ];

        $entry = [
            'aci' => $aci,
            'associateddomain' => $domain->namespace,
            'inetdomainbasedn' => $domainBaseDN,
            'objectclass' => [
                'top',
                'domainrelatedobject',
                'inetdomain'
            ],
        ];

        $dn = "associateddomain={$domain->namespace},{$config['domain_base_dn']}";

        self::setDomainAttributes($domain, $entry);

        if (!$ldap->get_entry($dn)) {
            $result = $ldap->add_entry($dn, $entry);

            if (!$result) {
                self::throwException($ldap, "Failed to create a domain in LDAP");
            }
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
            . '(userdn != "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN
            . ' || ldap:///ou=People,' . $domainBaseDN . '??sub?(objectclass=inetorgperson)") '
            . 'AND NOT roledn = "ldap:///cn=kolab-admin,' . $mgmtRootDN . '";)',

            '(targetattr != "userPassword")'
            . '(version 3.0;acl "Search Access";allow (read,compare,search,write)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN
            . ' || ldap:///ou=People,' . $domainBaseDN . '??sub?(objectclass=inetorgperson)");)',

            '(targetattr = "*")'
            . '(version 3.0;acl "Kolab Administrators";allow (all)'
            . '(roledn = "ldap:///cn=kolab-admin,' . $domainBaseDN
            . ' || ldap:///cn=kolab-admin,' . $mgmtRootDN . '");)',

            '(target = "ldap:///ou=*,' . $domainBaseDN . '")'
            . '(targetattr="objectclass || aci || ou")'
            . '(version 3.0;acl "Allow Domain sub-OU Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN . '");)',

            '(target = "ldap:///uid=*,ou=People,' . $domainBaseDN . '")(targetattr="*")'
            . '(version 3.0;acl "Allow Domain First User Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN . '");)',

            '(target = "ldap:///cn=*,' . $domainBaseDN . '")(targetattr="objectclass || cn")'
            . '(version 3.0;acl "Allow Domain Role Registration"; allow (add)'
            . '(userdn = "ldap:///uid=kolab-service,ou=Special Users,' . $mgmtRootDN . '");)',
        );

        if (!$ldap->get_entry($domainBaseDN)) {
            $ldap->add_entry($domainBaseDN, $entry);
        }

        foreach (['Groups', 'People', 'Resources', 'Shared Folders'] as $item) {
            if (!$ldap->get_entry("ou={$item},{$domainBaseDN}")) {
                $ldap->add_entry(
                    "ou={$item},{$domainBaseDN}",
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

        foreach (['kolab-admin'] as $item) {
            if (!$ldap->get_entry("cn={$item},{$domainBaseDN}")) {
                $ldap->add_entry(
                    "cn={$item},{$domainBaseDN}",
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

        // TODO: Assign kolab-admin role to the owner?

        if (empty(self::$ldap)) {
            $ldap->close();
        }
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
     * @throws \Exception
     */
    public static function createUser(User $user): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $entry = [
            'objectclass' => [
                'top',
                'inetorgperson',
                'inetuser',
                'kolabinetorgperson',
                'mailrecipient',
                'person'
            ],
            'mail' => $user->email,
            'uid' => $user->email,
            'nsroledn' => []
        ];

        if (!self::getUserEntry($ldap, $user->email, $dn) && $dn) {
            self::setUserAttributes($user, $entry);

            $result = $ldap->add_entry($dn, $entry);

            if (!$result) {
                self::throwException($ldap, "Failed to create a user in LDAP");
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Delete a domain from LDAP.
     *
     * @param \App\Domain $domain The domain to update.
     *
     * @throws \Exception
     */
    public static function deleteDomain(Domain $domain): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $hostedRootDN = \config('ldap.hosted.root_dn');
        $mgmtRootDN = \config('ldap.admin.root_dn');

        $domainBaseDN = "ou={$domain->namespace},{$hostedRootDN}";

        if ($ldap->get_entry($domainBaseDN)) {
            $result = $ldap->delete_entry_recursive($domainBaseDN);

            if (!$result) {
                self::throwException($ldap, "Failed to delete a domain from LDAP");
            }
        }

        if ($ldap_domain = $ldap->find_domain($domain->namespace)) {
            if ($ldap->get_entry($ldap_domain['dn'])) {
                $result = $ldap->delete_entry($ldap_domain['dn']);

                if (!$result) {
                    self::throwException($ldap, "Failed to delete a domain from LDAP");
                }
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Delete a user from LDAP.
     *
     * @param \App\User $user The user account to update.
     *
     * @throws \Exception
     */
    public static function deleteUser(User $user): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        if (self::getUserEntry($ldap, $user->email, $dn)) {
            $result = $ldap->delete_entry($dn);

            if (!$result) {
                self::throwException($ldap, "Failed to delete a user from LDAP");
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Get a domain data from LDAP.
     *
     * @param string $namespace The domain name
     *
     * @return array|false|null
     * @throws \Exception
     */
    public static function getDomain(string $namespace)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $ldapDomain = $ldap->find_domain($namespace);

        if ($ldapDomain) {
            $domain = $ldap->get_entry($ldapDomain['dn']);
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }

        return $domain ?? null;
    }

    /**
     * Get a user data from LDAP.
     *
     * @param string $email The user email.
     *
     * @return array|false|null
     * @throws \Exception
     */
    public static function getUser(string $email)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $user = self::getUserEntry($ldap, $email, $dn, true);

        if (empty(self::$ldap)) {
            $ldap->close();
        }

        return $user;
    }

    /**
     * Update a domain in LDAP.
     *
     * @param \App\Domain $domain The domain to update.
     *
     * @throws \Exception
     */
    public static function updateDomain(Domain $domain): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $ldapDomain = $ldap->find_domain($domain->namespace);

        if (!$ldapDomain) {
            self::throwException($ldap, "Failed to update a domain in LDAP (domain not found)");
        }

        $oldEntry = $ldap->get_entry($ldapDomain['dn']);
        $newEntry = $oldEntry;

        self::setDomainAttributes($domain, $newEntry);

        if (array_key_exists('inetdomainstatus', $newEntry)) {
            $newEntry['inetdomainstatus'] = (string) $newEntry['inetdomainstatus'];
        }

        $result = $ldap->modify_entry($ldapDomain['dn'], $oldEntry, $newEntry);

        if (!is_array($result)) {
            self::throwException($ldap, "Failed to update a domain in LDAP");
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Update a user in LDAP.
     *
     * @param \App\User $user The user account to update.
     *
     * @throws \Exception
     */
    public static function updateUser(User $user): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $newEntry = $oldEntry = self::getUserEntry($ldap, $user->email, $dn, true);

        if (!$oldEntry) {
            self::throwException($ldap, "Failed to update a user in LDAP (user not found)");
        }

        self::setUserAttributes($user, $newEntry);

        if (array_key_exists('objectclass', $newEntry)) {
            if (!in_array('inetuser', $newEntry['objectclass'])) {
                $newEntry['objectclass'][] = 'inetuser';
            }
        }

        if (array_key_exists('inetuserstatus', $newEntry)) {
            $newEntry['inetuserstatus'] = (string) $newEntry['inetuserstatus'];
        }

        if (array_key_exists('mailquota', $newEntry)) {
            $newEntry['mailquota'] = (string) $newEntry['mailquota'];
        }

        $result = $ldap->modify_entry($dn, $oldEntry, $newEntry);

        if (!is_array($result)) {
            self::throwException($ldap, "Failed to update a user in LDAP");
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Initialize connection to LDAP
     */
    private static function initLDAP(array $config, string $privilege = 'admin')
    {
        if (self::$ldap) {
            return self::$ldap;
        }

        $ldap = new \Net_LDAP3($config);

        $connected = $ldap->connect();

        if (!$connected) {
            throw new \Exception("Failed to connect to LDAP");
        }

        $bound = $ldap->bind(\config("ldap.{$privilege}.bind_dn"), \config("ldap.{$privilege}.bind_pw"));

        if (!$bound) {
            throw new \Exception("Failed to bind to LDAP");
        }

        return $ldap;
    }

    /**
     * Set domain attributes
     */
    private static function setDomainAttributes(Domain $domain, array &$entry)
    {
        $entry['inetdomainstatus'] = $domain->status;
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
        $entry['inetuserstatus'] = $user->status;
        $entry['o'] = $user->getSetting('organization');
        $entry['mailquota'] = 0;
        $entry['alias'] = $user->aliases->pluck('alias')->toArray();

        $roles = [];

        foreach ($user->entitlements as $entitlement) {
            \Log::debug("Examining {$entitlement->sku->title}");

            switch ($entitlement->sku->title) {
                case "mailbox":
                    break;

                case "storage":
                    $entry['mailquota'] += 1048576;
                    break;

                default:
                    $roles[] = $entitlement->sku->title;
                    break;
            }
        }

        $hostedRootDN = \config('ldap.hosted.root_dn');

        $entry['nsroledn'] = [];

        if (in_array("2fa", $roles)) {
            $entry['nsroledn'][] = "cn=2fa-user,{$hostedRootDN}";
        }

        if (in_array("activesync", $roles)) {
            $entry['nsroledn'][] = "cn=activesync-user,{$hostedRootDN}";
        }

        if (!in_array("groupware", $roles)) {
            $entry['nsroledn'][] = "cn=imap-user,{$hostedRootDN}";
        }
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
     * Get user entry from LDAP.
     *
     * @param \Net_LDAP3 $ldap  Ldap connection
     * @param string     $email User email (uid)
     * @param string     $dn    Reference to user DN
     * @param bool       $full  Get extra attributes, e.g. nsroledn
     *
     * @return false|null|array User entry, False on error, NULL if not found
     */
    private static function getUserEntry($ldap, $email, &$dn = null, $full = false)
    {
        list($_local, $_domain) = explode('@', $email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return $domain;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "uid={$email},ou=People,{$base_dn}";

        $entry = $ldap->get_entry($dn);

        if ($entry && $full) {
            if (!array_key_exists('nsroledn', $entry)) {
                $roles = $ldap->get_entry_attributes($dn, ['nsroledn']);
                if (!empty($roles)) {
                    $entry['nsroledn'] = (array) $roles['nsroledn'];
                }
            }
        }

        return $entry ?: null;
    }

    /**
     * Logging callback
     */
    public static function logHook($level, $msg): void
    {
        if (
            (
                $level == LOG_INFO
                || $level == LOG_DEBUG
                || $level == LOG_NOTICE
            )
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

    /**
     * Throw exception and close the connection when needed
     *
     * @param \Net_LDAP3 $ldap    Ldap connection
     * @param string     $message Exception message
     *
     * @throws \Exception
     */
    private static function throwException($ldap, string $message): void
    {
        if (empty(self::$ldap) && !empty($ldap)) {
            $ldap->close();
        }

        throw new \Exception($message);
    }
}
