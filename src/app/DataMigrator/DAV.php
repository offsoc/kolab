<?php

namespace App\DataMigrator;

use App\Utils;
use App\Backends\DAV as DAVClient;
use App\Backends\DAV\Opaque as DAVOpaque;
use App\Backends\DAV\Folder as DAVFolder;

class DAV
{
    /** @var DAVClient DAV Backend */
    protected $client;

    /** @var array Settings */
    protected $settings;


    /**
     * Object constructor
     */
    public function __construct(Account $account)
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
     * Create an object.
     *
     * @param string $filename File location
     * @param string $folder   Folder name
     * @param string $type     Folder type
     *
     * @throws \Exception
     */
    public function createObjectFromFile(string $filename, string $folder, string $type)
    {
        $href = $this->getFolderPath($folder, $type) . '/' . pathinfo($filename, PATHINFO_BASENAME);

        $object = new DAVOpaque($filename);
        $object->href = $href;

        switch ($type) {
            case Engine::TYPE_EVENT:
            case Engine::TYPE_TASK:
                $object->contentType = 'text/calendar; charset=utf-8';
                break;

            case Engine::TYPE_CONTACT:
            case Engine::TYPE_GROUP:
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
     * @param string $folder Name of a folder with full path
     * @param string $type   Folder type
     *
     * @throws \Exception on error
     */
    public function createFolder(string $folder, string $type): void
    {
        $dav_type = $this->type2DAV($type);
        $folders = $this->client->listFolders($dav_type);

        if ($folders === false) {
            throw new \Exception("Failed to list folders on the DAV server");
        }

        // Note: iRony flattens the list by modifying the folder name
        // This is not going to work with Cyrus DAV, but anyway folder
        // hierarchies support is not full in Kolab 4.
        foreach ($folders as $dav_folder) {
            if (str_replace(' » ', '/', $dav_folder->name) === $folder) {
                // do nothing, folder already exists
                return;
            }
        }

        $home = $this->client->getHome($dav_type);
        $folder_id = Utils::uuidStr();
        $collection_type = $dav_type == DAVClient::TYPE_VCARD ? 'addressbook' : 'calendar';

        // We create all folders on the top-level
        $dav_folder = new DAVFolder();
        $dav_folder->name = $folder;
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
    protected function getFolderPath(string $folder, string $type): string
    {
        $folders = $this->client->listFolders($this->type2DAV($type));

        if ($folders === false) {
            throw new \Exception("Failed to list folders on the DAV server");
        }

        // Note: iRony flattens the list by modifying the folder name
        // This is not going to work with Cyrus DAV, but anyway folder
        // hierarchies support is not full in Kolab 4.
        foreach ($folders as $dav_folder) {
            if (str_replace(' » ', '/', $dav_folder->name) === $folder) {
                return rtrim($dav_folder->href, '/');
            }
        }

        throw new \Exception("Folder not found: {$folder}");
    }

    /**
     * Map Kolab type into DAV object type
     */
    static protected function type2DAV(string $type): string
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
