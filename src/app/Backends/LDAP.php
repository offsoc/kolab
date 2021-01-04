<?php

namespace App\Backends;

use App\Domain;
use App\Group;
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
                self::throwException(
                    $ldap,
                    "Failed to create domain {$domain->namespace} in LDAP (" . __LINE__ . ")"
                );
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
            $result = $ldap->add_entry($domainBaseDN, $entry);

            if (!$result) {
                self::throwException(
                    $ldap,
                    "Failed to create domain {$domain->namespace} in LDAP (" . __LINE__ . ")"
                );
            }
        }

        foreach (['Groups', 'People', 'Resources', 'Shared Folders'] as $item) {
            if (!$ldap->get_entry("ou={$item},{$domainBaseDN}")) {
                $result = $ldap->add_entry(
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

                if (!$result) {
                    self::throwException(
                        $ldap,
                        "Failed to create domain {$domain->namespace} in LDAP (" . __LINE__ . ")"
                    );
                }
            }
        }

        foreach (['kolab-admin'] as $item) {
            if (!$ldap->get_entry("cn={$item},{$domainBaseDN}")) {
                $result = $ldap->add_entry(
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

                if (!$result) {
                    self::throwException(
                        $ldap,
                        "Failed to create domain {$domain->namespace} in LDAP (" . __LINE__ . ")"
                    );
                }
            }
        }

        // TODO: Assign kolab-admin role to the owner?

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Create a group in LDAP.
     *
     * @param \App\Group $group The group to create.
     *
     * @throws \Exception
     */
    public static function createGroup(Group $group): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        list($cn, $domainName) = explode('@', $group->email);

        $domain = $group->domain();

        if (empty($domain)) {
            self::throwException(
                $ldap,
                "Failed to create group {$group->email} in LDAP (" . __LINE__ . ")"
            );
        }

        $hostedRootDN = \config('ldap.hosted.root_dn');

        $domainBaseDN = "ou={$domain->namespace},{$hostedRootDN}";

        $groupBaseDN = "ou=Groups,{$domainBaseDN}";

        $dn = "cn={$cn},{$groupBaseDN}";

        $entry = [
            'cn' => $cn,
            'mail' => $group->email,
            'objectclass' => [
                'top',
                'groupofuniquenames',
                'kolabgroupofuniquenames'
            ],
            'uniqueMember' => []
        ];

        self::setGroupAttributes($ldap, $group, $entry);

        $result = $ldap->add_entry($dn, $entry);

        if (!$result) {
            self::throwException(
                $ldap,
                "Failed to create group {$group->email} in LDAP (" . __LINE__ . ")"
            );
        }

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

        if (!self::getUserEntry($ldap, $user->email, $dn)) {
            if (empty($dn)) {
                self::throwException($ldap, "Failed to create user {$user->email} in LDAP (" . __LINE__ . ")");
            }

            self::setUserAttributes($user, $entry);

            $result = $ldap->add_entry($dn, $entry);

            if (!$result) {
                self::throwException(
                    $ldap,
                    "Failed to create user {$user->email} in LDAP (" . __LINE__ . ")"
                );
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Delete a domain from LDAP.
     *
     * @param \App\Domain $domain The domain to delete
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
                self::throwException(
                    $ldap,
                    "Failed to delete domain {$domain->namespace} from LDAP (" . __LINE__ . ")"
                );
            }
        }

        if ($ldap_domain = $ldap->find_domain($domain->namespace)) {
            if ($ldap->get_entry($ldap_domain['dn'])) {
                $result = $ldap->delete_entry($ldap_domain['dn']);

                if (!$result) {
                    self::throwException(
                        $ldap,
                        "Failed to delete domain {$domain->namespace} from LDAP (" . __LINE__ . ")"
                    );
                }
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Delete a group from LDAP.
     *
     * @param \App\Group $group The group to delete.
     *
     * @throws \Exception
     */
    public static function deleteGroup(Group $group): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        if (self::getGroupEntry($ldap, $group->email, $dn)) {
            $result = $ldap->delete_entry($dn);

            if (!$result) {
                self::throwException(
                    $ldap,
                    "Failed to delete group {$group->email} from LDAP (" . __LINE__ . ")"
                );
            }
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Delete a user from LDAP.
     *
     * @param \App\User $user The user account to delete.
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
                self::throwException(
                    $ldap,
                    "Failed to delete user {$user->email} from LDAP (" . __LINE__ . ")"
                );
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
     * Get a group data from LDAP.
     *
     * @param string $email The group email.
     *
     * @return array|false|null
     * @throws \Exception
     */
    public static function getGroup(string $email)
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        $group = self::getGroupEntry($ldap, $email, $dn);

        if (empty(self::$ldap)) {
            $ldap->close();
        }

        return $group;
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
            self::throwException(
                $ldap,
                "Failed to update domain {$domain->namespace} in LDAP (domain not found)"
            );
        }

        $oldEntry = $ldap->get_entry($ldapDomain['dn']);
        $newEntry = $oldEntry;

        self::setDomainAttributes($domain, $newEntry);

        if (array_key_exists('inetdomainstatus', $newEntry)) {
            $newEntry['inetdomainstatus'] = (string) $newEntry['inetdomainstatus'];
        }

        $result = $ldap->modify_entry($ldapDomain['dn'], $oldEntry, $newEntry);

        if (!is_array($result)) {
            self::throwException(
                $ldap,
                "Failed to update domain {$domain->namespace} in LDAP (" . __LINE__ . ")"
            );
        }

        if (empty(self::$ldap)) {
            $ldap->close();
        }
    }

    /**
     * Update a group in LDAP.
     *
     * @param \App\Group $group The group to update
     *
     * @throws \Exception
     */
    public static function updateGroup(Group $group): void
    {
        $config = self::getConfig('admin');
        $ldap = self::initLDAP($config);

        list($cn, $domainName) = explode('@', $group->email);

        $domain = $group->domain();

        if (empty($domain)) {
            self::throwException(
                $ldap,
                "Failed to update group {$group->email} in LDAP (" . __LINE__ . ")"
            );
        }

        $hostedRootDN = \config('ldap.hosted.root_dn');

        $domainBaseDN = "ou={$domain->namespace},{$hostedRootDN}";

        $groupBaseDN = "ou=Groups,{$domainBaseDN}";

        $dn = "cn={$cn},{$groupBaseDN}";

        $entry = [
            'cn' => $cn,
            'mail' => $group->email,
            'objectclass' => [
                'top',
                'groupofuniquenames',
                'kolabgroupofuniquenames'
            ],
            'uniqueMember' => []
        ];

        $oldEntry = $ldap->get_entry($dn);

        self::setGroupAttributes($ldap, $group, $entry);

        $result = $ldap->modify_entry($dn, $oldEntry, $entry);

        if (!is_array($result)) {
            self::throwException(
                $ldap,
                "Failed to update group {$group->email} in LDAP (" . __LINE__ . ")"
            );
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
            self::throwException(
                $ldap,
                "Failed to update user {$user->email} in LDAP (user not found)"
            );
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
            self::throwException(
                $ldap,
                "Failed to update user {$user->email} in LDAP (" . __LINE__ . ")"
            );
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

        $bound = $ldap->bind(
            \config("ldap.{$privilege}.bind_dn"),
            \config("ldap.{$privilege}.bind_pw")
        );

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
     * Convert group member addresses in to valid entries.
     */
    private static function setGroupAttributes($ldap, Group $group, &$entry)
    {
        $validMembers = [];

        $domain = $group->domain();

        $hostedRootDN = \config('ldap.hosted.root_dn');

        $domainBaseDN = "ou={$domain->namespace},{$hostedRootDN}";

        foreach ($group->members as $member) {
            list($local, $domainName) = explode('@', $member);

            $memberDN = "uid={$member},ou=People,{$domainBaseDN}";

            // if the member is in the local domain but doesn't exist, drop it
            if ($domainName == $domain->namespace) {
                if (!$ldap->get_entry($memberDN)) {
                    continue;
                }
            }

            // add the member if not in the local domain
            if (!$ldap->get_entry($memberDN)) {
                $memberEntry = [
                    'cn' => $member,
                    'mail' => $member,
                    'objectclass' => [
                        'top',
                        'inetorgperson',
                        'organizationalperson',
                        'person'
                    ],
                    'sn' => 'unknown'
                ];

                $ldap->add_entry($memberDN, $memberEntry);
            }

            $entry['uniquemember'][] = $memberDN;
            $validMembers[] = $member;
        }

        // Update members in sql (some might have been removed),
        // skip model events to not invoke another update job
        $group->members = $validMembers;
        Group::withoutEvents(function () use ($group) {
            $group->save();
        });
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
     * Get group entry from LDAP.
     *
     * @param \Net_LDAP3 $ldap  Ldap connection
     * @param string     $email Group email (mail)
     * @param string     $dn    Reference to group DN
     *
     * @return false|null|array Group entry, False on error, NULL if not found
     */
    private static function getGroupEntry($ldap, $email, &$dn = null)
    {
        list($_local, $_domain) = explode('@', $email, 2);

        $domain = $ldap->find_domain($_domain);

        if (!$domain) {
            return $domain;
        }

        $base_dn = $ldap->domain_root_dn($_domain);
        $dn = "cn={$_local},ou=Groups,{$base_dn}";

        $entry = $ldap->get_entry($dn);

        return $entry ?: null;
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
