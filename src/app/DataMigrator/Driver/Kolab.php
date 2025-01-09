<?php

namespace App\DataMigrator\Driver;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;

/**
 * Data migration from/to a Kolab server
 */
class Kolab extends IMAP
{
    protected const CTYPE_KEY         = '/shared/vendor/kolab/folder-type';
    protected const CTYPE_KEY_PRIVATE = '/private/vendor/kolab/folder-type';
    protected const DAV_TYPES = [
        Engine::TYPE_CONTACT,
        Engine::TYPE_EVENT,
        Engine::TYPE_TASK,
    ];

    /** @var DAV DAV importer/exporter engine */
    protected $davDriver;

    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        // TODO: Right now we require IMAP server and DAV host names, but for Kolab we should be able
        // to detect IMAP and DAV locations, e.g. so we can just provide "kolabnow.com" as an input
        // Note: E.g. KolabNow uses different hostname for DAV, pass it as a query parameter 'dav_host'.

        // Setup IMAP connection
        $uri = (string) $account;
        $uri = preg_replace('/^kolab:/', 'tls:', $uri);

        parent::__construct(new Account($uri), $engine);

        // Setup DAV connection
        $uri = sprintf(
            'davs://%s:%s@%s',
            urlencode($account->username),
            urlencode($account->password),
            $account->params['dav_host'] ?? $account->host,
        );

        $this->davDriver = new DAV(new Account($uri), $engine);
    }

    /**
     * Authenticate
     */
    public function authenticate(): void
    {
        // IMAP
        parent::authenticate();

        // DAV
        $this->davDriver->authenticate();
    }

    /**
     * Create a folder.
     *
     * @param Folder $folder Folder data
     *
     * @throws \Exception on error
     */
    public function createFolder(Folder $folder): void
    {
        // IMAP
        if ($folder->type == Engine::TYPE_MAIL) {
            parent::createFolder($folder);
            return;
        }

        // DAV
        if (in_array($folder->type, self::DAV_TYPES)) {
            $this->davDriver->createFolder($folder);
            return;
        }
    }

    /**
     * Create an item in a folder.
     *
     * @param Item $item Item to import
     *
     * @throws \Exception
     */
    public function createItem(Item $item): void
    {
        // IMAP
        if ($item->folder->type == Engine::TYPE_MAIL) {
            parent::createItem($item);
            return;
        }

        // DAV
        if (in_array($item->folder->type, self::DAV_TYPES)) {
            $this->davDriver->createItem($item);
            return;
        }

        // TODO: Configuration (v3 tags)
    }

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void
    {
        // IMAP
        if ($item->folder->type == Engine::TYPE_MAIL) {
            parent::fetchItem($item);
            return;
        }

        // DAV
        if (in_array($item->folder->type, self::DAV_TYPES)) {
            $this->davDriver->fetchItem($item);
            return;
        }

        // TODO: Configuration (v3 tags)
    }

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void
    {
        // IMAP
        if ($folder->type == Engine::TYPE_MAIL) {
            parent::fetchItemList($folder, $callback, $importer);
            return;
        }

        // DAV
        if (in_array($folder->type, self::DAV_TYPES)) {
            $this->davDriver->fetchItemList($folder, $callback, $importer);
            return;
        }

        // TODO: Configuration (v3 tags)
    }

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array
    {
        // Using only IMAP to get the list of all folders works with Kolab v3, but not v4.
        // We could use IMAP, extract the XML, convert to iCal/vCard format and pass to DAV.
        // But it will be easier to use DAV for contact/task/event folders migration.
        $result = [];

        // Get DAV folders
        if (empty($types) || count(array_intersect($types, self::DAV_TYPES)) > 0) {
            $result = $this->davDriver->getFolders($types);
        }

        if (!empty($types) && count(array_intersect($types, [Engine::TYPE_MAIL, Engine::TYPE_CONFIGURATION])) == 0) {
            return $result;
        }

        // Get IMAP (mail and configuration) folders
        $folders = $this->imap->listMailboxes('', '');

        if ($folders === false) {
            throw new \Exception("Failed to get list of IMAP folders");
        }

        $metadata = $this->imap->getMetadata('*', [self::CTYPE_KEY, self::CTYPE_KEY_PRIVATE]);

        if ($metadata === null) {
            throw new \Exception("Failed to get METADATA for IMAP folders. Not a Kolab server?");
        }

        $configuration_folders = [];

        foreach ($folders as $folder) {
            $type = 'mail';
            if (!empty($metadata[$folder][self::CTYPE_KEY_PRIVATE])) {
                $type = $metadata[$folder][self::CTYPE_KEY_PRIVATE];
            } elseif (!empty($metadata[$folder][self::CTYPE_KEY])) {
                $type = $metadata[$folder][self::CTYPE_KEY];
            }

            [$type] = explode('.', $type);

            // These types we do not support
            if ($type != Engine::TYPE_MAIL && $type != Engine::TYPE_CONFIGURATION) {
                continue;
            }

            if (!empty($types) && !in_array($type, $types)) {
                continue;
            }

            if ($this->shouldSkip($folder)) {
                \Log::debug("Skipping folder {$folder}.");
                continue;
            }

            $folder = Folder::fromArray([
                'fullname' => $folder,
                'type' => $type,
            ]);

            if ($type == Engine::TYPE_CONFIGURATION) {
                $configuration_folders[] = $folder;
            } else {
                $result[] = $folder;
            }
        }

        // Put configuration folders at the end of the list
        // Migrating tags requires all members already migrated
        if (!empty($configuration_folders)) {
            $result = array_merge($result, $configuration_folders);
        }

        return $result;
    }

    /**
     * Get a list of folder items, limited to their essential propeties
     * used in incremental migration to skip unchanged items.
     */
    public function getItems(Folder $folder): array
    {
        // IMAP
        if ($folder->type == Engine::TYPE_MAIL) {
            return parent::getItems($folder);
        }

        // DAV
        if (in_array($folder->type, self::DAV_TYPES)) {
            return $this->davDriver->getItems($folder);
        }

        // Configuration folder (v3 tags)
        if ($folder->type == Engine::TYPE_CONFIGURATION) {
            // X-Kolab-Type: application/x-vnd.kolab.configuration.relation
            // TODO
        }

        return [];
    }
}
