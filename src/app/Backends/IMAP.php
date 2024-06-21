<?php

namespace App\Backends;

use App\Group;
use App\Resource;
use App\SharedFolder;
use App\User;
use App\Utils;

class IMAP
{
    /** @const array Group settings used by the backend */
    public const GROUP_SETTINGS = [];

    /** @const array Resource settings used by the backend */
    public const RESOURCE_SETTINGS = [
        'folder',
        'invitation_policy',
    ];

    /** @const array Shared folder settings used by the backend */
    public const SHARED_FOLDER_SETTINGS = [
        'folder',
        'acl',
    ];

    /** @const array User settings used by the backend */
    public const USER_SETTINGS = [];


    /** @const array Maps Kolab permissions to IMAP permissions */
    private const ACL_MAP = [
        'read-only' => 'lrs',
        'read-write' => 'lrswitedn',
        'full' => 'lrswipkxtecdn',
    ];

    /**
     * Delete a group.
     *
     * @param \App\Group $group Group
     *
     * @return bool True if a group was deleted successfully, False otherwise
     * @throws \Exception
     */
    public static function deleteGroup(Group $group): bool
    {
        $domainName = explode('@', $group->email, 2)[1];

        // Cleanup ACL
        // FIXME: Since all groups in Kolab4 have email address,
        //        should we consider using it in ACL instead of the name?
        //        Also we need to decide what to do and configure IMAP appropriately,
        //        right now groups in ACL does not work for me at all.
        // Commented out in favor of a nightly cleanup job, for performance reasons
        // \App\Jobs\IMAP\AclCleanupJob::dispatch($group->name, $domainName);

        return true;
    }

    /**
     * Create a mailbox.
     *
     * @param \App\User $user User
     *
     * @return bool True if a mailbox was created successfully, False otherwise
     * @throws \Exception
     */
    public static function createUser(User $user): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $mailbox = self::toUTF7('user/' . $user->email);

        // Mailbox already exists
        if (self::folderExists($imap, $mailbox)) {
            $imap->closeConnection();
            self::createDefaultFolders($user);
            return true;
        }

        // Create the mailbox
        if (!$imap->createFolder($mailbox)) {
            \Log::error("Failed to create mailbox {$mailbox}");
            $imap->closeConnection();
            return false;
        }

        // Wait until it's propagated (for Cyrus Murder setup)
        // FIXME: Do we still need this?
        if (strpos($imap->conn->data['GREETING'] ?? '', 'Cyrus IMAP Murder') !== false) {
            $tries = 30;
            while ($tries-- > 0) {
                $folders = $imap->listMailboxes('', $mailbox);
                if (is_array($folders) && count($folders)) {
                    break;
                }
                sleep(1);
                $imap->closeConnection();
                $imap = self::initIMAP($config);
            }
        }

        // Set quota
        $quota = $user->countEntitlementsBySku('storage') * 1048576;
        if ($quota) {
            $imap->setQuota($mailbox, ['storage' => $quota]);
        }

        self::createDefaultFolders($user);

        $imap->closeConnection();

        return true;
    }

    /**
     * Create default folders for the user.
     *
     * @param \App\User $user User
     */
    public static function createDefaultFolders(User $user): void
    {
        if ($defaultFolders = \config('imap.default_folders')) {
            $config = self::getConfig();
            // Log in as user to set private annotations and subscription state
            $imap = self::initIMAP($config, $user->email);
            foreach ($defaultFolders as $name => $folderconfig) {
                try {
                    $mailbox = self::toUTF7($name);
                    self::createFolder($imap, $mailbox, true, $folderconfig['metadata']);
                } catch (\Exception $e) {
                    \Log::warning("Failed to create the default folder. " . $e->getMessage());
                }
            }
            $imap->closeConnection();
        }
    }

    /**
     * Delete a mailbox.
     *
     * @param \App\User $user User
     *
     * @return bool True if a mailbox was deleted successfully, False otherwise
     * @throws \Exception
     */
    public static function deleteUser(User $user): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $mailbox = self::toUTF7('user/' . $user->email);

        // To delete the mailbox cyrus-admin needs extra permissions
        $imap->setACL($mailbox, $config['user'], 'c');

        // Delete the mailbox (no need to delete subfolders?)
        $result = $imap->deleteFolder($mailbox);

        if (!$result) {
            // Ignore the error if the folder doesn't exist (maybe it was removed already).
            if (!self::folderExists($imap, $mailbox)) {
                \Log::info("The mailbox to delete was already removed: $mailbox");
                $result = true;
            }
        }

        $imap->closeConnection();

        // Cleanup ACL
        // Commented out in favor of a nightly cleanup job, for performance reasons
        // \App\Jobs\IMAP\AclCleanupJob::dispatch($user->email);

        return $result;
    }

    /**
     * Update a mailbox (quota).
     *
     * @param \App\User $user User
     *
     * @return bool True if a mailbox was updated successfully, False otherwise
     * @throws \Exception
     */
    public static function updateUser(User $user): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $mailbox = self::toUTF7('user/' . $user->email);
        $result = true;

        // Set quota
        $quota = $user->countEntitlementsBySku('storage') * 1048576;
        if ($quota) {
            $result = $imap->setQuota($mailbox, ['storage' => $quota]);
        }

        $imap->closeConnection();

        return $result;
    }

    /**
     * Create a resource.
     *
     * @param \App\Resource $resource Resource
     *
     * @return bool True if a resource was created successfully, False otherwise
     * @throws \Exception
     */
    public static function createResource(Resource $resource): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $resource->getSettings(['invitation_policy', 'folder']);
        $mailbox = self::toUTF7($settings['folder']);
        $metadata = ['/shared/vendor/kolab/folder-type' => 'event'];

        $acl = [];
        if (!empty($settings['invitation_policy'])) {
            if (preg_match('/^manual:(\S+@\S+)$/', $settings['invitation_policy'], $m)) {
                $acl = ["{$m[1]}, full"];
            }
        }

        self::createFolder($imap, $mailbox, false, $metadata, Utils::ensureAclPostPermission($acl));

        $imap->closeConnection();

        return true;
    }

    /**
     * Update a resource.
     *
     * @param \App\Resource $resource Resource
     * @param array         $props    Old resource properties
     *
     * @return bool True if a resource was updated successfully, False otherwise
     * @throws \Exception
     */
    public static function updateResource(Resource $resource, array $props = []): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $resource->getSettings(['invitation_policy', 'folder']);
        $folder = $settings['folder'];
        $mailbox = self::toUTF7($folder);

        // Rename the mailbox (only possible if we have the old folder)
        if (!empty($props['folder']) && $props['folder'] != $folder) {
            $oldMailbox = self::toUTF7($props['folder']);

            if (!$imap->renameFolder($oldMailbox, $mailbox)) {
                \Log::error("Failed to rename mailbox {$oldMailbox} to {$mailbox}");
                $imap->closeConnection();
                return false;
            }
        }

        // ACL
        $acl = [];
        if (!empty($settings['invitation_policy'])) {
            if (preg_match('/^manual:(\S+@\S+)$/', $settings['invitation_policy'], $m)) {
                $acl = ["{$m[1]}, full"];
            }
        }

        self::aclUpdate($imap, $mailbox, Utils::ensureAclPostPermission($acl));

        $imap->closeConnection();

        return true;
    }

    /**
     * Delete a resource.
     *
     * @param \App\Resource $resource Resource
     *
     * @return bool True if a resource was deleted successfully, False otherwise
     * @throws \Exception
     */
    public static function deleteResource(Resource $resource): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $resource->getSettings(['folder']);
        $mailbox = self::toUTF7($settings['folder']);

        // To delete the mailbox cyrus-admin needs extra permissions
        $imap->setACL($mailbox, $config['user'], 'c');

        // Delete the mailbox (no need to delete subfolders?)
        $result = $imap->deleteFolder($mailbox);

        $imap->closeConnection();

        return $result;
    }

    /**
     * Create a shared folder.
     *
     * @param \App\SharedFolder $folder Shared folder
     *
     * @return bool True if a falder was created successfully, False otherwise
     * @throws \Exception
     */
    public static function createSharedFolder(SharedFolder $folder): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $folder->getSettings(['acl', 'folder']);
        $acl = !empty($settings['acl']) ? json_decode($settings['acl'], true) : [];
        $mailbox = self::toUTF7($settings['folder']);
        $metadata = ['/shared/vendor/kolab/folder-type' => $folder->type];

        self::createFolder($imap, $mailbox, false, $metadata, Utils::ensureAclPostPermission($acl));

        $imap->closeConnection();

        return true;
    }

    /**
     * Update a shared folder.
     *
     * @param \App\SharedFolder $folder Shared folder
     * @param array             $props  Old folder properties
     *
     * @return bool True if a falder was updated successfully, False otherwise
     * @throws \Exception
     */
    public static function updateSharedFolder(SharedFolder $folder, array $props = []): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $folder->getSettings(['acl', 'folder']);
        $acl = !empty($settings['acl']) ? json_decode($settings['acl'], true) : [];
        $folder = $settings['folder'];
        $mailbox = self::toUTF7($folder);

        // Rename the mailbox
        if (!empty($props['folder']) && $props['folder'] != $folder) {
            $oldMailbox = self::toUTF7($props['folder']);

            if (!$imap->renameFolder($oldMailbox, $mailbox)) {
                \Log::error("Failed to rename mailbox {$oldMailbox} to {$mailbox}");
                $imap->closeConnection();
                return false;
            }
        }

        // Note: Shared folder type does not change

        // ACL
        self::aclUpdate($imap, $mailbox, Utils::ensureAclPostPermission($acl));

        $imap->closeConnection();

        return true;
    }

    /**
     * Delete a shared folder.
     *
     * @param \App\SharedFolder $folder Shared folder
     *
     * @return bool True if a falder was deleted successfully, False otherwise
     * @throws \Exception
     */
    public static function deleteSharedFolder(SharedFolder $folder): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $settings = $folder->getSettings(['folder']);
        $mailbox = self::toUTF7($settings['folder']);

        // To delete the mailbox cyrus-admin needs extra permissions
        $imap->setACL($mailbox, $config['user'], 'c');

        // Delete the mailbox
        $result = $imap->deleteFolder($mailbox);

        $imap->closeConnection();

        return $result;
    }

    /**
     * Check if a shared folder is set up.
     *
     * @param string $folder Folder name, e.g. shared/Resources/Name@domain.tld
     *
     * @return bool True if a folder exists and is set up, False otherwise
     */
    public static function verifySharedFolder(string $folder): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        // Convert the folder from UTF8 to UTF7-IMAP
        if (\preg_match('#^(shared/|shared/Resources/)(.+)(@[^@]+)$#', $folder, $matches)) {
            $folderName = self::toUTF7($matches[2]);
            $folder = $matches[1] . $folderName . $matches[3];
        }

        // FIXME: just listMailboxes() does not return shared folders at all

        $metadata = $imap->getMetadata($folder, ['/shared/vendor/kolab/folder-type']);

        $imap->closeConnection();

        // Note: We have to use error code to distinguish an error from "no mailbox" response

        if ($imap->errornum === \rcube_imap_generic::ERROR_NO) {
            return false;
        }

        if ($imap->errornum !== \rcube_imap_generic::ERROR_OK) {
            throw new \Exception("Failed to get folder metadata from IMAP");
        }

        return true;
    }

    /**
     * Convert UTF8 string to UTF7-IMAP encoding
     */
    public static function toUTF7(string $string): string
    {
        return \mb_convert_encoding($string, 'UTF7-IMAP', 'UTF8');
    }

    /**
     * Check if an account is set up
     *
     * @param string $username User login (email address)
     *
     * @return bool True if an account exists and is set up, False otherwise
     */
    public static function verifyAccount(string $username): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        $mailbox = self::toUTF7('user/' . $username);

        // Mailbox already exists
        if (self::folderExists($imap, $mailbox)) {
            $imap->closeConnection();
            return true;
        }

        $imap->closeConnection();
        return false;
    }

    /**
     * Check if an account is set up
     *
     * @param string $username User login (email address)
     *
     * @return bool True if an account exists and is set up, False otherwise
     */
    public static function verifyDefaultFolders(string $username): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config, $username);

        foreach (\config('imap.default_folders') as $mb => $_metadata) {
            $mailbox = self::toUTF7($mb);
            if (!self::folderExists($imap, $mailbox)) {
                $imap->closeConnection();
                return false;
            }
        }

        $imap->closeConnection();
        return true;
    }

    /**
     * Check if we can connect to the imap server
     *
     * @return bool True on success
     */
    public static function healthcheck(): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);
        $imap->closeConnection();
        return true;
    }

    /**
     * Remove ACL for a specified user/group anywhere in the IMAP
     *
     * @param string $ident  ACL identifier (user email or e.g. group name)
     * @param string $domain ACL domain
     */
    public static function aclCleanup(string $ident, string $domain = ''): void
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        if (strpos($ident, '@')) {
            $domain = explode('@', $ident, 2)[1];
        }

        $callback = function ($folder) use ($imap, $ident) {
            $acl = $imap->getACL($folder);
            if (is_array($acl) && isset($acl[$ident])) {
                \Log::info("Cleanup: Removing {$ident} from ACL on {$folder}");
                $imap->deleteACL($folder, $ident);
            }
        };

        $folders = $imap->listMailboxes('', "user/*@{$domain}");

        if (!is_array($folders)) {
            $imap->closeConnection();
            throw new \Exception("Failed to get IMAP folders");
        }

        array_walk($folders, $callback);

        $folders = $imap->listMailboxes('', "shared/*@{$domain}");

        if (!is_array($folders)) {
            $imap->closeConnection();
            throw new \Exception("Failed to get IMAP folders");
        }

        array_walk($folders, $callback);

        $imap->closeConnection();
    }

    /**
     * Remove ACL entries pointing to non-existent users/groups, for a specified domain
     *
     * @param string $domain  Domain namespace
     * @param bool   $dry_run Output ACL entries to delete, but do not delete
     */
    public static function aclCleanupDomain(string $domain, bool $dry_run = false): void
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config);

        // Collect available (existing) users/groups
        // FIXME: Should we limit this to the requested domain or account?
        // FIXME: For groups should we use name or email?
        $idents = User::pluck('email')
            // ->concat(Group::pluck('name'))
            ->concat(['anyone', 'anonymous', $config['user']])
            ->all();

        $callback = function ($folder) use ($imap, $idents, $dry_run) {
            $acl = $imap->getACL($folder);
            if (is_array($acl)) {
                $owner = null;
                if (preg_match('|^user/([^/@]+).*@([^@/]+)$|', $folder, $m)) {
                    $owner = $m[1] . '@' . $m[2];
                }
                foreach (array_keys($acl) as $key) {
                    if ($owner && $key === $owner) {
                        // Don't even try to remove the folder's owner entry
                        continue;
                    }
                    if (!in_array($key, $idents)) {
                        if ($dry_run) {
                            echo "{$folder} {$key} {$acl[$key]}\n";
                        } else {
                            \Log::info("Cleanup: Removing {$key} from ACL on {$folder}");
                            $imap->deleteACL($folder, $key);
                        }
                    }
                }
            }
        };

        $folders = $imap->listMailboxes('', "user/*@{$domain}");

        if (!is_array($folders)) {
            $imap->closeConnection();
            throw new \Exception("Failed to get IMAP folders");
        }

        array_walk($folders, $callback);

        $folders = $imap->listMailboxes('', "shared/*@{$domain}");

        if (!is_array($folders)) {
            $imap->closeConnection();
            throw new \Exception("Failed to get IMAP folders");
        }

        array_walk($folders, $callback);

        $imap->closeConnection();
    }

    /**
     * Create a folder and set some default properties
     *
     * @param \rcube_imap_generic $imap The imap instance
     * @param string $mailbox Mailbox name
     * @param bool $subscribe Subscribe to the folder
     * @param array $metadata Metadata to set on the folder
     * @param array $acl Acl to set on the folder
     *
     * @return bool True when having a folder created, False if it already existed.
     * @throws \Exception
     */
    private static function createFolder($imap, string $mailbox, $subscribe = false, $metadata = null, $acl = null)
    {
        if (self::folderExists($imap, $mailbox)) {
            return false;
        }

        if (!$imap->createFolder($mailbox)) {
            throw new \Exception("Failed to create mailbox {$mailbox}");
        }

        if (!empty($acl)) {
            self::aclUpdate($imap, $mailbox, $acl, true);
        }

        if ($subscribe) {
            $imap->subscribe($mailbox);
        }

        foreach ($metadata as $key => $value) {
            $imap->setMetadata($mailbox, [$key => $value]);
        }

        return true;
    }

    /**
     * Convert Kolab ACL into IMAP user->rights array
     */
    private static function aclToImap($acl): array
    {
        if (empty($acl)) {
            return [];
        }

        return \collect($acl)
            ->mapWithKeys(function ($item, $key) {
                list($user, $rights) = explode(',', $item, 2);
                $rights = trim($rights);
                return [trim($user) => self::ACL_MAP[$rights] ?? $rights];
            })
            ->all();
    }

    /**
     * Update folder ACL
     */
    private static function aclUpdate($imap, $mailbox, $acl, bool $isNew = false)
    {
        $imapAcl = $isNew ? [] : $imap->getACL($mailbox);

        if (is_array($imapAcl)) {
            foreach (self::aclToImap($acl) as $user => $rights) {
                if (empty($imapAcl[$user]) || implode('', $imapAcl[$user]) !== $rights) {
                    $imap->setACL($mailbox, $user, $rights);
                }

                unset($imapAcl[$user]);
            }

            foreach ($imapAcl as $user => $rights) {
                $imap->deleteACL($mailbox, $user);
            }
        }
    }

    /**
     * Check if an IMAP folder exists
     */
    private static function folderExists($imap, string $folder): bool
    {
        $folders = $imap->listMailboxes('', $folder);

        if (!is_array($folders)) {
            $imap->closeConnection();
            throw new \Exception("Failed to get IMAP folders");
        }

        return count($folders) > 0;
    }

    /**
     * Initialize connection to IMAP
     */
    private static function initIMAP(array $config, string $login_as = null)
    {
        $imap = new \rcube_imap_generic();

        if (\config('app.debug')) {
            $imap->setDebug(true, 'App\Backends\IMAP::logDebug');
        }

        if ($login_as) {
            $config['options']['auth_cid'] = $config['user'];
            $config['options']['auth_pw'] = $config['password'];
            $config['options']['auth_type'] = 'PLAIN';
            $config['user'] = $login_as;
        }

        $imap->connect($config['host'], $config['user'], $config['password'], $config['options']);

        if (!$imap->connected()) {
            $message = sprintf("Login failed for %s against %s. %s", $config['user'], $config['host'], $imap->error);

            \Log::error($message);

            throw new \Exception("Connection to IMAP failed");
        }

        return $imap;
    }

    /**
     * Get LDAP configuration for specified access level
     */
    private static function getConfig()
    {
        $uri = \parse_url(\config('imap.uri'));
        $default_port = 143;
        $ssl_mode = null;

        if (isset($uri['scheme'])) {
            if (preg_match('/^(ssl|imaps)/', $uri['scheme'])) {
                $default_port = 993;
                $ssl_mode = 'ssl';
            } elseif ($uri['scheme'] === 'tls') {
                $ssl_mode = 'tls';
            }
        }

        $config = [
            'host' => $uri['host'],
            'user' => \config('imap.admin_login'),
            'password' => \config('imap.admin_password'),
            'options' => [
                'port' => !empty($uri['port']) ? $uri['port'] : $default_port,
                'ssl_mode' => $ssl_mode,
                'socket_options' => [
                    'ssl' => [
                        'verify_peer' => \config('imap.verify_peer'),
                        'verify_peer_name' => \config('imap.verify_peer'),
                        'verify_host' => \config('imap.verify_host')
                    ],
                ],
            ],
        ];

        return $config;
    }

    /**
     * Debug logging callback
     */
    public static function logDebug($conn, $msg): void
    {
        $msg = '[IMAP] ' . $msg;

        \Log::debug($msg);
    }
}
