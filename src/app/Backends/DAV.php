<?php

namespace App\Backends;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class DAV
{
    public const TYPE_VEVENT = 'VEVENT';
    public const TYPE_VTODO = 'VTODO';
    public const TYPE_VCARD = 'VCARD';
    public const TYPE_NOTIFICATION = 'NOTIFICATION';

    public const NAMESPACES = [
        self::TYPE_VEVENT => 'urn:ietf:params:xml:ns:caldav',
        self::TYPE_VTODO => 'urn:ietf:params:xml:ns:caldav',
        self::TYPE_VCARD => 'urn:ietf:params:xml:ns:carddav',
    ];

    protected $url;
    protected $user;
    protected $password;
    protected $responseHeaders = [];
    protected $homes;

    /**
     * Object constructor
     */
    public function __construct($user, $password, $url = null)
    {
        $this->url      = $url ?: \config('services.dav.uri');
        $this->user     = $user;
        $this->password = $password;
    }

    /**
     * Discover DAV home (root) collection of a specified type.
     *
     * @return array|false Home locations or False on error
     */
    public function discover()
    {
        if (is_array($this->homes)) {
            return $this->homes;
        }

        $path = parse_url($this->url, PHP_URL_PATH);

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
                . '<d:prop>'
                    . '<d:current-user-principal />'
                . '</d:prop>'
            . '</d:propfind>';

        // Note: Cyrus CardDAV service requires Depth:1 (CalDAV works without it)
        $response = $this->request('', 'PROPFIND', $body, ['Depth' => 1, 'Prefer' => 'return-minimal']);

        if (empty($response)) {
            \Log::error("Failed to get current-user-principal from the DAV server.");
            return false;
        }

        $elements = $response->getElementsByTagName('response');
        $principal_href = '';

        foreach ($elements as $element) {
            foreach ($element->getElementsByTagName('current-user-principal') as $prop) {
                $principal_href = $prop->nodeValue;
                break;
            }
        }

        if ($path && str_starts_with($principal_href, $path)) {
            $principal_href = substr($principal_href, strlen($path));
        }

        $ns = [
            'xmlns:d="DAV:"',
            'xmlns:cal="urn:ietf:params:xml:ns:caldav"',
            'xmlns:card="urn:ietf:params:xml:ns:carddav"',
        ];

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind ' . implode(' ', $ns) . '>'
                . '<d:prop>'
                    . '<cal:calendar-home-set/>'
                    . '<card:addressbook-home-set/>'
                    . '<d:notification-URL/>'
                . '</d:prop>'
            . '</d:propfind>';

        $response = $this->request($principal_href, 'PROPFIND', $body);

        if (empty($response)) {
            \Log::error("Failed to get home collections from the DAV server.");
            return false;
        }

        $elements = $response->getElementsByTagName('response');
        $homes = [];

        if ($element = $response->getElementsByTagName('response')->item(0)) {
            if ($prop = $element->getElementsByTagName('prop')->item(0)) {
                foreach ($prop->childNodes as $home) {
                    if ($home->firstChild && $home->firstChild->localName == 'href') {
                        $href = $home->firstChild->nodeValue;

                        if ($path && str_starts_with($href, $path)) {
                            $href = substr($href, strlen($path));
                        }

                        $homes[$home->localName] = $href;
                    }
                }
            }
        }

        return $this->homes = $homes;
    }

    /**
     * Get user home folder of specified type
     *
     * @param string $type Home type or component name
     *
     * @return string|null Folder location href
     */
    public function getHome($type)
    {
        $options = [
            self::TYPE_VEVENT => 'calendar-home-set',
            self::TYPE_VTODO => 'calendar-home-set',
            self::TYPE_VCARD => 'addressbook-home-set',
            self::TYPE_NOTIFICATION => 'notification-URL',
        ];

        $homes = $this->discover();

        if (is_array($homes) && isset($options[$type])) {
            return $homes[$options[$type]] ?? null;
        }

        return null;
    }

    /**
     * Check if we can connect to the DAV server
     *
     * @return bool True on success, False otherwise
     */
    public static function healthcheck(): bool
    {
        // TODO
        return true;
    }

    /**
     * Get list of folders of specified type.
     *
     * @param string $component Component to filter by (VEVENT, VTODO, VCARD)
     *
     * @return false|array<DAV\Folder> List of folders' metadata or False on error
     */
    public function listFolders(string $component)
    {
        $root_href = $this->getHome($component);

        if ($root_href === null) {
            return false;
        }

        $ns    = 'xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/"';
        $props = '';

        if ($component != self::TYPE_VCARD) {
            $ns .= ' xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:a="http://apple.com/ns/ical/" xmlns:k="Kolab:"';
            $props = '<c:supported-calendar-component-set />'
                . '<a:calendar-color />'
                . '<k:alarms />';
        }

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind ' . $ns . '>'
                . '<d:prop>'
                    . '<d:resourcetype />'
                    . '<d:displayname />'
                    . '<d:owner/>'
                    . '<cs:getctag />'
                    . $props
                . '</d:prop>'
            . '</d:propfind>';

        // Note: Cyrus CardDAV service requires Depth:1 (CalDAV works without it)
        $headers =  ['Depth' => 1, 'Prefer' => 'return-minimal'];

        $response = $this->request($root_href, 'PROPFIND', $body, $headers);

        if (empty($response)) {
            \Log::error("Failed to get folders list from the DAV server.");
            return false;
        }

        $folders = [];

        foreach ($response->getElementsByTagName('response') as $element) {
            $folder = DAV\Folder::fromDomElement($element);

            // Note: Addressbooks don't have 'type' specified
            if (
                ($component == self::TYPE_VCARD && in_array('addressbook', $folder->types))
                || in_array($component, $folder->components)
            ) {
                $folders[] = $folder;
            }
        }

        return $folders;
    }

    /**
     * Create a DAV object in a folder
     *
     * @param DAV\CommonObject $object Object
     *
     * @return false|DAV\CommonObject Object on success, False on error
     */
    public function create(DAV\CommonObject $object)
    {
        $headers = ['Content-Type' => $object->contentType];

        $content = (string) $object;

        if (!strlen($content)) {
            throw new \Exception("Cannot PUT an empty DAV object");
        }

        $response = $this->request($object->href, 'PUT', $content, $headers);

        if ($response !== false) {
            if (!empty($this->responseHeaders['ETag'])) {
                $etag = $this->responseHeaders['ETag'][0];
                if (preg_match('|^".*"$|', $etag)) {
                    $etag = substr($etag, 1, -1);
                }

                $object->etag = $etag;
            }

            return $object;
        }

        return false;
    }

    /**
     * Update a DAV object in a folder
     *
     * @param DAV\CommonObject $object Object
     *
     * @return false|DAV\CommonObject Object on success, False on error
     */
    public function update(DAV\CommonObject $object)
    {
        return $this->create($object);
    }

    /**
     * Delete a DAV object from a folder
     *
     * @param string $location Object location
     *
     * @return bool True on success, False on error
     */
    public function delete(string $location)
    {
        $response = $this->request($location, 'DELETE', '', ['Depth' => 1, 'Prefer' => 'return-minimal']);

        return $response !== false;
    }

    /**
     * Create a DAV folder (collection)
     *
     * @param DAV\Folder $folder Folder object
     *
     * @return bool True on success, False on error
     */
    public function folderCreate(DAV\Folder $folder)
    {
        $response = $this->request($folder->href, 'MKCOL', $folder->toXML('mkcol'));

        return $response !== false;
    }

    /**
     * Delete a DAV folder (collection)
     *
     * @param string $location Folder location
     *
     * @return bool True on success, False on error
     */
    public function folderDelete($location)
    {
        $response = $this->request($location, 'DELETE');

        return $response !== false;
    }

    /**
     * Get all properties of a folder.
     *
     * @param string $location Object location
     *
     * @return false|DAV\Folder Folder metadata or False on error
     */
    public function folderInfo(string $location)
    {
        $body = DAV\Folder::propfindXML();

        // Note: Cyrus CardDAV service requires Depth:1 (CalDAV works without it)
        $response = $this->request($location, 'PROPFIND', $body, ['Depth' => 0, 'Prefer' => 'return-minimal']);

        if (!empty($response) && ($element = $response->getElementsByTagName('response')->item(0))) {
            return DAV\Folder::fromDomElement($element);
        }

        return false;
    }

    /**
     * Update a DAV folder (collection)
     *
     * @param DAV\Folder $folder Folder object
     *
     * @return bool True on success, False on error
     */
    public function folderUpdate(DAV\Folder $folder)
    {
        // Note: Changing resourcetype property is forbidden (at least by Cyrus)

        $response = $this->request($folder->href, 'PROPPATCH', $folder->toXML('propertyupdate'));

        return $response !== false;
    }

    /**
     * Initialize default DAV folders (collections)
     *
     * @param \App\User $user User object
     *
     * @throws \Exception
     */
    public static function initDefaultFolders(\App\User $user): void
    {
        if (!\config('services.dav.uri')) {
            return;
        }

        $folders = \config('services.dav.default_folders');
        if (!count($folders)) {
            return;
        }

        // Cyrus DAV does not support proxy authorization via DAV. Even though it has
        // the Authorize-As header, it is used only for cummunication with Murder backends.
        // We use a one-time token instead. It's valid for 10 seconds, assume it's enough time.
        $password = \App\Auth\Utils::tokenCreate((string) $user->id);

        if ($password === null) {
            throw new \Exception("Failed to create an authentication token for DAV");
        }

        $dav = new self($user->email, $password);

        foreach ($folders as $props) {
            $folder = new DAV\Folder();
            $folder->href = $props['type'] . 's' . '/user/' . $user->email . '/' . $props['path'];
            $folder->types = ['collection', $props['type']];
            $folder->name = $props['displayname'] ?? '';
            $folder->components = $props['components'] ?? [];

            $existing = null;
            try {
                $existing = $dav->folderInfo($folder->href);
            } catch (RequestException $e) {
                // Cyrus DAV returns 503 Service Unavailable on a non-existing location (?)
                if ($e->getCode() != 503 && $e->getCode() != 404) {
                    throw $e;
                }
            }

            // folder already exists? check the properties and update if needed
            if ($existing) {
                if ($existing->name != $folder->name || $existing->components != $folder->components) {
                    if (!$dav->folderUpdate($folder)) {
                        throw new \Exception("Failed to update DAV folder {$folder->href}");
                    }
                }
            } elseif (!$dav->folderCreate($folder)) {
                throw new \Exception("Failed to create DAV folder {$folder->href}");
            }
        }
    }

    /**
     * Check server options (and authentication)
     *
     * @return false|array DAV capabilities on success, False on error
     */
    public function options()
    {
        $response = $this->request('', 'OPTIONS');

        if ($response !== false) {
            return preg_split('/,\s+/', implode(',', $this->responseHeaders['DAV'] ?? []));
        }

        return false;
    }

    /**
     * Search DAV objects in a folder.
     *
     * @param string     $location     Folder location
     * @param DAV\Search $search       Search request parameters
     * @param callable   $callback     A callback to execute on every item
     * @param bool       $opaque       Return objects as instances of DAV\Opaque
     * @param array      $extraHeaders Extra headers for the REPORT request
     *
     * @return false|array List of objects on success, False on error
     */
    public function search(string $location, DAV\Search $search, $callback = null, $opaque = false, $extraHeaders = [])
    {
        $headers = array_merge(
            ['Depth' => $search->depth, 'Prefer' => 'return-minimal'],
            $extraHeaders
        );

        $response = $this->request($location, 'REPORT', $search, $headers);

        if (empty($response)) {
            \Log::error("Failed to get objects from the DAV server.");
            return false;
        }

        $objects = [];

        foreach ($response->getElementsByTagName('response') as $element) {
            if ($opaque) {
                $object = DAV\Opaque::fromDomElement($element);
            } else {
                $object = $this->objectFromElement($element, $search->component);
            }

            if ($callback) {
                $object = $callback($object);
            }

            if ($object) {
                if (is_array($object)) {
                    $objects[$object[0]] = $object[1];
                } else {
                    $objects[] = $object;
                }
            }
        }

        return $objects;
    }

    /**
     * Fetch DAV objects data from a folder
     *
     * @param string $location  Folder location
     * @param string $component Object type (VEVENT, VTODO, VCARD)
     * @param array  $hrefs     List of objects' locations to fetch
     *
     * @return false|array Objects metadata on success, False on error
     */
    public function getObjects(string $location, string $component, array $hrefs = [])
    {
        if (empty($hrefs)) {
            return [];
        }

        $body = '';
        foreach ($hrefs as $href) {
            $body .= '<d:href>' . $href . '</d:href>';
        }

        $queries = [
            self::TYPE_VEVENT => 'calendar-multiget',
            self::TYPE_VTODO => 'calendar-multiget',
            self::TYPE_VCARD => 'addressbook-multiget',
        ];

        $types = [
            self::TYPE_VEVENT => 'calendar-data',
            self::TYPE_VTODO => 'calendar-data',
            self::TYPE_VCARD => 'address-data',
        ];

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . ' <c:' . $queries[$component] . ' xmlns:d="DAV:" xmlns:c="' . self::NAMESPACES[$component] . '">'
                . '<d:prop>'
                    . '<d:getetag />'
                    . '<c:' . $types[$component] . ' />'
                . '</d:prop>'
                . $body
            . '</c:' . $queries[$component] . '>';

        $response = $this->request($location, 'REPORT', $body, ['Depth' => 1, 'Prefer' => 'return-minimal']);

        if (empty($response)) {
            \Log::error("Failed to get objects from the DAV server.");
            return false;
        }

        $objects = [];

        foreach ($response->getElementsByTagName('response') as $element) {
            $objects[] = $this->objectFromElement($element, $component);
        }

        return $objects;
    }

    /**
     * Parse XML content
     */
    protected function parseXML($xml)
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');

        if (str_starts_with($xml, '<?xml')) {
            if (!$doc->loadXML($xml)) {
                throw new \Exception("Failed to parse XML");
            }

            $doc->formatOutput = true;
        }

        return $doc;
    }

    /**
     * Parse request/response body for debug purposes
     */
    protected function debugBody($body, $headers)
    {
        $head = '';

        foreach ($headers as $header_name => $header_value) {
            if (is_array($header_value)) {
                $header_value = implode("\n\t", $header_value);
            }

            $head .= "{$header_name}: {$header_value}\n";
        }

        if ($body instanceof DAV\CommonObject) {
            $body = (string) $body;
        }

        if (str_starts_with($body, '<?xml')) {
            $doc = new \DOMDocument('1.0', 'UTF-8');

            $doc->formatOutput = true;
            $doc->preserveWhiteSpace = false;

            if (!$doc->loadXML($body)) {
                throw new \Exception("Failed to parse XML");
            }

            $body = $doc->saveXML();
        }

        return $head . (is_string($body) && strlen($body) > 0 ? "\n{$body}" : '');
    }

    /**
     * Create DAV\CommonObject from a DOMElement
     */
    protected function objectFromElement($element, $component)
    {
        switch ($component) {
            case self::TYPE_VEVENT:
                $object = DAV\Vevent::fromDomElement($element);
                break;
            case self::TYPE_VTODO:
                $object = DAV\Vtodo::fromDomElement($element);
                break;
            case self::TYPE_VCARD:
                $object = DAV\Vcard::fromDomElement($element);
                break;
            default:
                throw new \Exception("Unknown component: {$component}");
        }

        return $object;
    }

    /**
     * Execute HTTP request to a DAV server
     */
    protected function request($path, $method, $body = '', $headers = [])
    {
        $debug = \config('app.debug');
        $url = trim($this->url, '/');

        $this->responseHeaders = [];

        // Remove the duplicate path prefix
        if ($path) {
            $rootPath = parse_url($url, PHP_URL_PATH);
            $path = '/' . ltrim($path, '/');

            if ($rootPath && str_starts_with($path, $rootPath)) {
                $path = substr($path, strlen($rootPath));
            }

            $url .= $path;
        }

        $client = Http::withBasicAuth($this->user, $this->password)
            // ->withToken($token) // Bearer token
            ->withOptions(['verify' => \config('services.dav.verify')]);

        if ($body) {
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/xml; charset=utf-8';
            }

            $client->withBody($body, $headers['Content-Type']);
        }

        if (!empty($headers)) {
            $client->withHeaders($headers);
        }

        if ($debug) {
            $body = $this->debugBody($body, $headers);
            \Log::debug("C: {$method}: {$url}" . (strlen($body) > 0 ? "\n$body" : ''));
        }

        $response = $client->send($method, $url);

        $body = $response->body();
        $code = $response->status();

        if ($debug) {
            \Log::debug("S: [{$code}]\n" . $this->debugBody($body, $response->headers()));
        }

        // Throw an exception if a client or server error occurred...
        $response->throw();

        $this->responseHeaders = $response->headers();

        return $this->parseXML($body);
    }
}
