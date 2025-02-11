<?php

namespace App\DataMigrator\Driver;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;

class Test implements ExporterInterface, ImporterInterface
{
    protected const CHUNK_SIZE = 3;

    public static $fetchedItems = [];
    public static $createdItems = [];
    public static $createdFolders = [];
    public static $folders = [];

    /** @var Account Account to operate on */
    protected $account;

    /** @var Engine Data migrator engine */
    protected $engine;


    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        $this->engine = $engine;
        $this->account = $account;
    }

    public static function init($folders)
    {
        self::$folders = $folders;

        self::$fetchedItems = [];
        self::$createdItems = [];
        self::$createdFolders = [];
    }

    /**
     * Check user credentials.
     *
     * @throws \Exception
     */
    public function authenticate(): void
    {
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
        self::$createdItems[] = $item;
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
        self::$createdFolders[] = $folder;
    }

    /**
     * Fetching a folder metadata
     */
    public function fetchFolder(Folder $folder): void
    {
        // NOP
    }

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void
    {
        $item->content = 'content';
        $item->filename = 'test.eml';

        self::$fetchedItems[] = $item;
    }

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void
    {
        // Get existing messages' headers from the destination mailbox
        $existing = $importer->getItems($folder);

        $set = new ItemSet();

        foreach ((self::$folders[$folder->id]['items'] ?? []) as $itemId => $item) {
            $exists = null; // TODO

            $item['id'] = $itemId;
            $item['folder'] = $folder;
            $item['existing'] = $exists;

            $set->items[] = Item::fromArray($item);

            if (count($set->items) == self::CHUNK_SIZE) {
                $callback($set);
                $set = new ItemSet();
            }
        }

        if (count($set->items)) {
            $callback($set);
        }
    }

    /**
     * Get a list of items, limited to their essential propeties
     * used in incremental migration.
     *
     * @param Folder $folder Folder data
     *
     * @throws \Exception on error
     */
    public function getItems(Folder $folder): array
    {
        return self::$folders[$folder->id]['existing_items'] ?? [];
    }

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array
    {
        $result = [];

        foreach (self::$folders as $folderId => $folder) {
            // Skip folder types we do not support (need)
            if (!empty($types) && !in_array($folder['type'], $types)) {
                continue;
            }

            $folder['id'] = $folderId;
            $folder['total'] = count($folder['items']);

            $result[] = Folder::fromArray($folder);
        }

        return $result;
    }
}
