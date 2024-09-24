<?php

namespace App\DataMigrator;

use App\Backends\DAV as DAVClient;
use App\Backends\DAV\Opaque as DAVOpaque;
use App\Backends\DAV\Folder as DAVFolder;
use App\Backends\DAV\Search as DAVSearch;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;
use App\Utils;

class DAV implements ExporterInterface, ImporterInterface
{
    /** @const int Max number of items to migrate in one go */
    protected const CHUNK_SIZE = 25;

    /** @var DAVClient DAV Backend */
    protected $client;

    /** @var Account Account to operate on */
    protected $account;

    /** @var Engine Data migrator engine */
    protected $engine;

    /** @var array Folder paths cache */
    protected $folderPaths = [];

    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        $username = $account->username . ($account->loginas ? "**{$account->loginas}" : '');
        $baseUri = rtrim($account->uri, '/');
        $baseUri = preg_replace('|^dav|', 'http', $baseUri);

        $this->client = new DAVClient($username, $account->password, $baseUri);
        $this->engine = $engine;
        $this->account = $account;
    }

    /**
     * Check user credentials.
     *
     * @throws \Exception
     */
    public function authenticate(): void
    {
        try {
            $this->client->options();
        } catch (\Exception $e) {
            throw new \Exception("Invalid DAV credentials or server.");
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
        $is_file = false;
        $href = $item->existing ?: null;

        if (strlen($item->content)) {
            $content = $item->content;
        } else {
            $content = $item->filename;
            $is_file = true;
        }

        if (empty($href)) {
            $href = $this->getFolderPath($item->folder) . '/' . basename($item->filename);
        }

        $object = new DAVOpaque($content, $is_file);
        $object->href = $href;

        switch ($item->folder->type) {
            case Engine::TYPE_EVENT:
            case Engine::TYPE_TASK:
                $object->contentType = 'text/calendar; charset=utf-8';
                break;

            case Engine::TYPE_CONTACT:
                $object->contentType = 'text/vcard; charset=utf-8';
                break;
        }

        if ($this->client->create($object) === false) {
            throw new \Exception("Failed to save DAV object at {$href}");
        }
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
        $dav_type = $this->type2DAV($folder->type);
        $folders = $this->client->listFolders($dav_type);

        if ($folders === false) {
            throw new \Exception("Failed to list folders on the DAV server");
        }

        // Note: iRony flattens the list by modifying the folder name
        // This is not going to work with Cyrus DAV, but anyway folder
        // hierarchies support is not full in Kolab 4.
        foreach ($folders as $dav_folder) {
            if (str_replace(' » ', '/', $dav_folder->name) === $folder->fullname) {
                // do nothing, folder already exists
                return;
            }
        }

        $home = $this->client->getHome($dav_type);
        $folder_id = Utils::uuidStr();
        $collection_type = $dav_type == DAVClient::TYPE_VCARD ? 'addressbook' : 'calendar';

        // We create all folders on the top-level
        $dav_folder = new DAVFolder();
        $dav_folder->name = $folder->fullname;
        $dav_folder->href = rtrim($home, '/') . '/' . $folder_id;
        $dav_folder->components = [$dav_type];
        $dav_folder->types = ['collection', $collection_type];

        if ($this->client->folderCreate($dav_folder) === false) {
            throw new \Exception("Failed to create a DAV folder {$dav_folder->href}");
        }
    }

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void
    {
        $result = $this->client->getObjects(dirname($item->id), $this->type2DAV($item->folder->type), [$item->id]);

        if ($result === false) {
            throw new \Exception("Failed to fetch DAV item for {$item->id}");
        }

        // TODO: Do any content changes, e.g. organizer/attendee email migration

        $content = (string) $result[0];

        if (strlen($content) > Engine::MAX_ITEM_SIZE) {
            // Save the item content to a file
            $location = $item->folder->tempFileLocation(basename($item->id));

            if (file_put_contents($location, $content) === false) {
                throw new \Exception("Failed to write to {$location}");
            }

            $item->filename = $location;
        } else {
            $item->content = $content;
            $item->filename = basename($item->id);
        }
    }

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void
    {
        // Get existing messages' headers from the destination mailbox
        $existing = $importer->getItems($folder);

        $dav_type = $this->type2DAV($folder->type);
        $location = $this->getFolderPath($folder);
        $search = new DAVSearch($dav_type, false);

        // TODO: We request only properties relevant to incremental migration,
        // i.e. to find that something exists and its last update time.
        // Some servers (iRony) do ignore that and return full VCARD/VEVENT/VTODO
        // content, if there's many objects we'll have a memory limit issue.
        // Also, this list should be controlled by the exporter.
        $search->dataProperties = ['UID', 'REV', 'DTSTAMP'];

        $set = new ItemSet();

        $result = $this->client->search(
            $location,
            $search,
            function ($item) use (&$set, $dav_type, $folder, $existing, $callback) {
                // Skip an item that exists and did not change
                $exists = null;
                if (!empty($existing[$item->uid])) {
                    $exists = $existing[$item->uid]['href'];
                    switch ($dav_type) {
                        case DAVClient::TYPE_VCARD:
                            if ($existing[$item->uid]['rev'] == $item->rev) {
                                return null;
                            }
                            break;
                        case DAVClient::TYPE_VEVENT:
                        case DAVClient::TYPE_VTODO:
                            if ($existing[$item->uid]['dtstamp'] == (string) $item->dtstamp) {
                                return null;
                            }
                            break;
                    }
                }

                $set->items[] = Item::fromArray([
                    'id' => $item->href,
                    'folder' => $folder,
                    'existing' => $exists,
                ]);

                if (count($set->items) == self::CHUNK_SIZE) {
                    $callback($set);
                    $set = new ItemSet();
                }

                return null;
            }
        );

        if (count($set->items)) {
            $callback($set);
        }

        if ($result === false) {
            throw new \Exception("Failed to get items from a DAV folder {$location}");
        }

        // TODO: Delete items that do not exist anymore?
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
        $dav_type = $this->type2DAV($folder->type);
        $location = $this->getFolderPath($folder);

        $search = new DAVSearch($dav_type);

        // TODO: We request only properties relevant to incremental migration,
        // i.e. to find that something exists and its last update time.
        // Some servers (iRony) do ignore that and return full VCARD/VEVENT/VTODO
        // content, if there's many objects we'll have a memory limit issue.
        // Also, this list should be controlled by the exporter.
        $search->dataProperties = ['UID', 'X-MS-ID', 'REV', 'DTSTAMP'];

        $items = $this->client->search(
            $location,
            $search,
            function ($item) use ($dav_type) {
                // Slim down the result to properties we might need
                $object = [
                    'href' => $item->href,
                    'uid' => $item->uid,
                ];

                if (!empty($item->custom['X-MS-ID'])) {
                    $object['x-ms-id'] = $item->custom['X-MS-ID'];
                }

                switch ($dav_type) {
                    case DAVClient::TYPE_VCARD:
                        $object['rev'] = $item->rev;
                        break;
                    case DAVClient::TYPE_VEVENT:
                    case DAVClient::TYPE_VTODO:
                        $object['dtstamp'] = (string) $item->dtstamp;
                        break;
                }

                return [$item->uid, $object];
            }
        );

        if ($items === false) {
            throw new \Exception("Failed to get items from a DAV folder {$location}");
        }

        return $items;
    }

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array
    {
        $result = [];
        foreach (['VEVENT', 'VTODO', 'VCARD'] as $component) {
            $type = $this->typeFromDAV($component);

            // Skip folder types we do not support (need)
            if (!empty($types) && !in_array($type, $types)) {
                continue;
            }

            $folders = $this->client->listFolders($component);

            foreach ($folders as $folder) {
                // Skip other users/shared folders
                if ($this->shouldSkip($folder)) {
                    continue;
                }

                $result[$folder->href] = Folder::fromArray([
                    'fullname' => str_replace(' » ', '/', $folder->name),
                    'href' => $folder->href,
                    'type' => $type,
                ]);
            }
        }

        return $result;
    }

    /**
     * Get folder relative URI
     */
    protected function getFolderPath(Folder $folder): string
    {
        $cache_key = $folder->type . '!' . $folder->fullname;
        if (isset($this->folderPaths[$cache_key])) {
            return $this->folderPaths[$cache_key];
        }

        for ($i = 0; $i < 5; $i++) {
            $folders = $this->client->listFolders($this->type2DAV($folder->type));

            if ($folders === false) {
                throw new \Exception("Failed to list folders on the DAV server");
            }

            // Note: iRony flattens the list by modifying the folder name
            // This is not going to work with Cyrus DAV, but anyway folder
            // hierarchies support is not full in Kolab 4.
            foreach ($folders as $dav_folder) {
                if (str_replace(' » ', '/', $dav_folder->name) === $folder->fullname) {
                    return $this->folderPaths[$cache_key] = rtrim($dav_folder->href, '/');
                }
            }
            sleep(1);
        }

        throw new \Exception("Folder not found: {$folder->fullname}");
    }

    /**
     * Map Kolab type into DAV object type
     */
    protected static function type2DAV(string $type): string
    {
        switch ($type) {
            case Engine::TYPE_EVENT:
                return DAVClient::TYPE_VEVENT;
            case Engine::TYPE_TASK:
                return DAVClient::TYPE_VTODO;
            case Engine::TYPE_CONTACT:
            case Engine::TYPE_GROUP:
                return DAVClient::TYPE_VCARD;
            default:
                throw new \Exception("Cannot map type '{$type}' to DAV");
        }
    }

    /**
     * Map DAV object type into Kolab type
     */
    protected static function typeFromDAV(string $type): string
    {
        switch ($type) {
            case DAVClient::TYPE_VEVENT:
                return Engine::TYPE_EVENT;
            case DAVClient::TYPE_VTODO:
                return Engine::TYPE_TASK;
            case DAVClient::TYPE_VCARD:
                // TODO what about groups
                return Engine::TYPE_CONTACT;
            default:
                throw new \Exception("Cannot map type '{$type}' from DAV");
        }
    }

    /**
     * Check if the folder should not be migrated
     */
    private function shouldSkip($folder): bool
    {
        // When dealing with iRony DAV other user folders names have distinct names
        // there's no other way to recognize them than by the name pattern.
        // ;et's hope that users do not have personal folders with names starting with a bracket.

        if (preg_match('~\(.*\) .*~', $folder->name)) {
            return true;
        }

        if (str_starts_with($folder->name, 'shared » ')) {
            return true;
        }

        // TODO: Cyrus DAV shared folders

        return false;
    }
}
