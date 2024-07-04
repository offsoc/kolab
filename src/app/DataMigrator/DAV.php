<?php

namespace App\DataMigrator;

use App\Backends\DAV as DAVClient;
use App\Backends\DAV\Opaque as DAVOpaque;
use App\Backends\DAV\Folder as DAVFolder;
use App\Backends\DAV\Search as DAVSearch;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\Item;
use App\Utils;

class DAV implements Interface\ImporterInterface
{
    /** @var DAVClient DAV Backend */
    protected $client;

    /** @var Account Account to operate on */
    protected $account;

    /** @var Engine Data migrator engine */
    protected $engine;

    /** @var array Settings */
    protected $settings;


    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        $username = $account->username . ($account->loginas ? "**{$account->loginas}" : '');
        $baseUri = rtrim($account->uri, '/');
        $baseUri = preg_replace('|^dav|', 'http', $baseUri);

        $this->settings = [
            'baseUri'  => $baseUri,
            'userName' => $username,
            'password' => $account->password,
        ];

        $this->client = new DAVClient($username, $account->password, $baseUri);
        $this->engine = $engine;
        $this->account = $account;
    }

    /**
     * Check user credentials.
     *
     * @throws \Exception
     */
    public function authenticate()
    {
        try {
            $result = $this->client->options();
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
        // TODO: For now we do DELETE + PUT. It's because the UID might have changed (which
        // is the case with e.g. contacts from EWS) causing a discrepancy between UID and href.
        // This is not necessarily a problem and would not happen to calendar events.
        // So, maybe we could improve that so DELETE is not needed.
        if ($item->existing) {
            try {
                $this->client->delete($item->existing);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // ignore 404 response, item removed in meantime?
                if ($e->getCode() != 404) {
                    throw $e;
                }
            }
        }

        $href = $this->getFolderPath($item->folder) . '/' . pathinfo($item->filename, PATHINFO_BASENAME);

        $object = new DAVOpaque($item->filename);
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
     * Get a list of folder items, limited to their essential propeties
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
        $search->dataProperties = ['UID', 'X-MS-ID', 'REV'];

        $items = $this->client->search(
            $location,
            $search,
            function ($item) use ($dav_type) {
                // Slim down the result to properties we might need
                $result = [
                    'href' => $item->href,
                    'uid' => $item->uid,
                    'x-ms-id' => $item->custom['X-MS-ID'] ?? null,
                ];
                /*
                switch ($dav_type) {
                    case DAVClient::TYPE_VCARD:
                        $result['rev'] = $item->rev;
                        break;
                }
                */

                return $result;
            }
        );

        if ($items === false) {
            throw new \Exception("Failed to get items from a DAV folder {$location}");
        }

        return $items;
    }

    /**
     * Get folder relative URI
     */
    protected function getFolderPath(Folder $folder): string
    {
        $folders = $this->client->listFolders($this->type2DAV($folder->type));

        if ($folders === false) {
            throw new \Exception("Failed to list folders on the DAV server");
        }

        // Note: iRony flattens the list by modifying the folder name
        // This is not going to work with Cyrus DAV, but anyway folder
        // hierarchies support is not full in Kolab 4.
        foreach ($folders as $dav_folder) {
            if (str_replace(' » ', '/', $dav_folder->name) === $folder->fullname) {
                return rtrim($dav_folder->href, '/');
            }
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
}
