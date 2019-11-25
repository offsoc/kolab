<?php

namespace App\DataMigrator;

use App\Utils;
use Sabre\DAV\Client;

class DAVClient
{
    const TYPE_TASK = 'task';
    const TYPE_EVENT = 'event';
    const TYPE_CONTACT = 'contact';

    /** @var Sabre\DAV\Client Engine */
    protected $client;

    /** @var array Settings */
    protected $settings;

    /** @var array List of all folders */
    protected $folders;


    /**
     * Object constructor
     */
    public function __construct(Account $account)
    {
        $username = $account->username . ($account->loginas ? "**{$account->loginas}" : '');

        $this->settings = [
            'baseUri'  => rtrim($account->uri, '/') . '/',
            'userName' => $username,
            'password' => $account->password,
            'authType' => Client::AUTH_BASIC,
        ];

        $this->client = new Client($this->settings);
    }

    /**
     * Check user credentials.
     *
     * @throws Exception
     */
    public function authenticate()
    {
        $result = $this->client->options();

        if (empty($result)) {
            throw new Exception("Invalid DAV credentials or server.");
        }
    }

    /**
     * Create an object.
     *
     * @param string $filename File location
     * @param array  $folder   Folder name
     *
     * @throws Exception
     */
    public function createObjectFromFile(string $filename, string $folder)
    {
        $data = fopen($filename, 'r');
/*
        // Need to tell Curl the attachments size, so it properly
        // sets Content-Length header, that is required in PUT
        // request by some webdav servers (#2978)
        $stat = fstat($data);
        $this->client->addCurlSetting(CURLOPT_INFILESIZE, $stat['size']);
*/
        $path = $this->getFolderPath($folder) . '/' . pathinfo($filename, PATHINFO_BASENAME);

        $response = $this->client->request('PUT', $path, $data);

        fclose($data);

        if ($response['statusCode'] != 201) {
            throw new \Exception("Storage error. " . $response['body']);
        }
    }

    /**
     * Create a folder.
     *
     * @param string $folder Name of a folder with full path
     * @param string $type   Folder type
     *
     * @throws Exception on error
     */
    public function createFolder(string $folder, string $type)
    {
        $folders = $this->listFolders();
        if (array_key_exists($folder, $folders)) {
            // do nothing, folder already exists
            return;
        }

        $types = [['DAV:', 'collection']];
        $prefix = '';

        if ($type == self::TYPE_CONTACT) {
            $types[] = ['urn:ietf:params:xml:ns:carddav', 'addressbook'];
            $prefix = 'addressbooks/' . urlencode($this->settings['userName']) . '/';
        } elseif ($type == self::TYPE_EVENT || $type == self::TYPE_TASK) {
            $types[] = ['urn:ietf:params:xml:ns:caldav', 'calendar'];
            $prefix = 'calendars/' . urlencode($this->settings['userName']) . '/';
        }

        // Create XML request
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElementNS('DAV:', 'mkcol');
        $set = $xml->createElementNS('DAV:', 'set');
        $prop = $xml->createElementNS('DAV:', 'prop');

        // Folder display name
        $prop->appendChild($xml->createElementNS('DAV:', 'displayname', $folder));

        // Folder type
        $resource_type = $xml->createElementNS('DAV:', 'resourcetype');
        foreach ($types as $rt) {
            $resource_type->appendChild($xml->createElementNS($rt[0], $rt[1]));
        }
        $prop->appendChild($resource_type);

        if ($type == self::TYPE_TASK) {
            // Extra property needed for task folders
            $cset = $xml->createElementNS('urn:ietf:params:xml:ns:caldav', 'supported-calendar-component-set');
            $comp = $xml->createElementNS('urn:ietf:params:xml:ns:caldav', 'comp');
            $comp->setAttribute('name', $type == self::TYPE_TASK ? 'VTODO' : 'VEVENT');
            $cset->appendChild($comp);
            $prop->appendChild($cset);
        }

        $xml->appendChild($root)->appendChild($set)->appendChild($prop);

        $body = $xml->saveXML();
        $folder_id = Utils::uuidStr();
        $path = $prefix . $folder_id;

        // Send the request
        $response = $this->client->request('MKCOL', $path, $body, ['Content-Type' => 'text/xml']);

        if ($response['statusCode'] != 201) {
            throw new \Exception("Storage error: " . $response['body']);
        }

        $this->folders[$folder] = [
            'id' => $folder_id,
            'type' => $type,
        ];
    }

    /**
     * Returns list of folders.
     *
     * @return array List of folders
     */
    public function listFolders(): array
    {
        if ($this->folders !== null) {
            return $this->folders;
        }

        $request = [
            '{DAV:}displayname',
            '{DAV:}resourcetype',
            '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set',
        ];

        // Get addressbook folders
        $root = 'addressbooks/' . urlencode($this->settings['userName']);
        $collections = $this->client->propFind($root, $request, 1);

        // Get calendar and task folders
        $root = 'calendars/' . urlencode($this->settings['userName']);
        $calendars = $this->client->propFind($root, $request, 'infinity');

        $collections = array_merge($collections, $calendars);
        $this->folders = [];

        foreach ($collections as $key => $props) {
            if ($type = $this->collectionType($props)) {
                $path = explode('/', rtrim($key, '/'));
                $id = $path[count($path)-1];

                // Note that in CalDAV/CardDAV folder names directly the same is in IMAP
                // especially talking about shared/other users folders
                $name = $props['{DAV:}displayname'];
                $name = str_replace(' » ', '/', $name);

                $this->folders[$name] = [
                    'id' => $id,
                    'type' => $type,
                ];
            }
        }

        return $this->folders;
    }

    /**
     * Detect folder type from collection properties.
     * Special collections (ldap addressbooks, calendar inbox/outbox) will be ignored
     */
    protected function collectionType(array $props)
    {
        if ($props['{DAV:}resourcetype']->is('{urn:ietf:params:xml:ns:caldav}calendar')) {
            foreach ($props['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'] as $set) {
                if (isset($set['attributes']) && isset($set['attributes']['name']) && $set['attributes']['name'] === 'VTODO') {
                    return self::TYPE_TASK;
                }
            }

            return self::TYPE_EVENT;
        }

        if ($props['{DAV:}resourcetype']->is('{urn:ietf:params:xml:ns:carddav}addressbook')
            && !$props['{DAV:}resourcetype']->is('{urn:ietf:params:xml:ns:carddav}directory')
        ) {
            return self::TYPE_CONTACT;
        }
    }

    /**
     * Get folder relative URI
     */
    protected function getFolderPath(string $folder): string
    {
        $folders = $this->listFolders();

        if (array_key_exists($folder, $folders)) {
            $data = $folders[$folder];

            if ($data['type'] == self::TYPE_CONTACT) {
                return 'addressbooks/' . urlencode($this->settings['userName']) . '/' . urlencode($data['id']);
            }

            if ($data['type'] == self::TYPE_EVENT || $data['type'] == self::TYPE_TASK) {
                return 'calendars/' . urlencode($this->settings['userName']) . '/' . urlencode($data['id']);
            }
        }

        throw new \Exception("Folder not found: {$folder}");
    }
}
