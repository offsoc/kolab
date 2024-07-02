<?php

namespace App\DataMigrator;

use App\Backends\DAV as DAVClient;
use App\Backends\DAV\Opaque as DAVOpaque;
use App\Backends\DAV\Folder as DAVFolder;
use App\DataMigrator\Interface\Folder;
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
     * @param string $filename File location
     * @param Folder $folder   Folder
     *
     * @throws \Exception
     */
    public function createItemFromFile(string $filename, Folder $folder): void
    {
        $href = $this->getFolderPath($folder) . '/' . pathinfo($filename, PATHINFO_BASENAME);

        $object = new DAVOpaque($filename);
        $object->href = $href;

        switch ($folder->type) {
            case Engine::TYPE_EVENT:
            case Engine::TYPE_TASK:
                $object->contentType = 'text/calendar; charset=utf-8';
                break;

            case Engine::TYPE_CONTACT:
                $object->contentType = 'text/vcard; charset=utf-8';
                break;
        }

        if ($this->client->create($object) === false) {
            throw new \Exception("Failed to save object into DAV server at {$href}");
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
