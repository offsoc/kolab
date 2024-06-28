<?php

namespace App\Backends;

use Illuminate\Support\Facades\Http;

class DAV
{
    public const TYPE_VEVENT = 'VEVENT';
    public const TYPE_VTODO = 'VTODO';
    public const TYPE_VCARD = 'VCARD';

    protected const NAMESPACES = [
        self::TYPE_VEVENT => 'urn:ietf:params:xml:ns:caldav',
        self::TYPE_VTODO => 'urn:ietf:params:xml:ns:caldav',
        self::TYPE_VCARD => 'urn:ietf:params:xml:ns:carddav',
    ];

    protected $url;
    protected $user;
    protected $password;
    protected $responseHeaders = [];

    /**
     * Object constructor
     */
    public function __construct($user, $password)
    {
        $this->url      = \config('services.dav.uri');
        $this->user     = $user;
        $this->password = $password;
    }

    /**
     * Discover DAV home (root) collection of a specified type.
     *
     * @param string $component Component to filter by (VEVENT, VTODO, VCARD)
     *
     * @return string|false Home collection location or False on error
     */
    public function discover(string $component = self::TYPE_VEVENT)
    {
        $roots = [
            self::TYPE_VEVENT => 'calendars',
            self::TYPE_VTODO => 'calendars',
            self::TYPE_VCARD => 'addressbooks',
        ];

        $homes = [
            self::TYPE_VEVENT => 'calendar-home-set',
            self::TYPE_VTODO => 'calendar-home-set',
            self::TYPE_VCARD => 'addressbook-home-set',
        ];

        $path = parse_url($this->url, PHP_URL_PATH);

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
                . '<d:prop>'
                    . '<d:current-user-principal />'
                . '</d:prop>'
            . '</d:propfind>';

        // Note: Cyrus CardDAV service requires Depth:1 (CalDAV works without it)
        $headers = ['Depth' => 1, 'Prefer' => 'return-minimal'];

        $response = $this->request('/' . $roots[$component], 'PROPFIND', $body, $headers);

        if (empty($response)) {
            \Log::error("Failed to get current-user-principal for {$component} from the DAV server.");
            return false;
        }

        $elements = $response->getElementsByTagName('response');

        foreach ($elements as $element) {
            foreach ($element->getElementsByTagName('prop') as $prop) {
                $principal_href = $prop->nodeValue;
                break;
            }
        }

        if (empty($principal_href)) {
            \Log::error("No principal on the DAV server.");
            return false;
        }

        if ($path && strpos($principal_href, $path) === 0) {
            $principal_href = substr($principal_href, strlen($path));
        }

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:" xmlns:c="' . self::NAMESPACES[$component] . '">'
                . '<d:prop>'
                    . '<c:' . $homes[$component] . ' />'
                . '</d:prop>'
            . '</d:propfind>';

        $response = $this->request($principal_href, 'PROPFIND', $body);

        if (empty($response)) {
            \Log::error("Failed to get homes for {$component} from the DAV server.");
            return false;
        }

        $root_href = false;
        $elements = $response->getElementsByTagName('response');

        foreach ($elements as $element) {
            foreach ($element->getElementsByTagName('prop') as $prop) {
                $root_href = $prop->nodeValue;
                break;
            }
        }

        if (!empty($root_href)) {
            if ($path && strpos($root_href, $path) === 0) {
                $root_href = substr($root_href, strlen($path));
            }
        }

        return $root_href;
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
        $root_href = $this->discover($component);

        if ($root_href === false) {
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

        $response = $this->request($object->href, 'PUT', $object, $headers);

        if ($response !== false) {
            if ($etag = $this->responseHeaders['etag']) {
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
     * Search DAV objects in a folder.
     *
     * @param string $location  Folder location
     * @param string $component Object type (VEVENT, VTODO, VCARD)
     *
     * @return false|array Objects metadata on success, False on error
     */
    public function search(string $location, string $component)
    {
        $queries = [
            self::TYPE_VEVENT => 'calendar-query',
            self::TYPE_VTODO => 'calendar-query',
            self::TYPE_VCARD => 'addressbook-query',
        ];

        $filter = '';
        if ($component != self::TYPE_VCARD) {
            $filter = '<c:comp-filter name="VCALENDAR">'
                    . '<c:comp-filter name="' . $component . '" />'
                . '</c:comp-filter>';
        }

        // TODO: Make filter an argument of this function to build all kind of queries.
        //       It probably should be a separate object e.g. DAV\Filter.
        // TODO: List of object props to return should also be an argument, so we not only
        //       could fetch "an index" but also any of object's data.

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . ' <c:' . $queries[$component] . ' xmlns:d="DAV:" xmlns:c="' . self::NAMESPACES[$component] . '">'
                . '<d:prop>'
                    . '<d:getetag />'
                . '</d:prop>'
                . ($filter ? "<c:filter>$filter</c:filter>" : '')
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
     * Fetch DAV objects data from a folder
     *
     * @param string $location  Folder location
     * @param string $component Object type (VEVENT, VTODO, VCARD)
     * @param array  $hrefs     List of objects' locations to fetch (empty for all objects)
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

        if (stripos($xml, '<?xml') === 0) {
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

        if (stripos($body, '<?xml') === 0) {
            $doc = new \DOMDocument('1.0', 'UTF-8');

            $doc->formatOutput = true;
            $doc->preserveWhiteSpace = false;

            if (!$doc->loadXML($body)) {
                throw new \Exception("Failed to parse XML");
            }

            $body = $doc->saveXML();
        }

        return $head . "\n" . rtrim($body);
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
        $url = $this->url;

        $this->responseHeaders = [];

        if ($path && ($rootPath = parse_url($url, PHP_URL_PATH)) && strpos($path, $rootPath) === 0) {
            $path = substr($path, strlen($rootPath));
        }

        $url .= $path;

        $client = Http::withBasicAuth($this->user, $this->password);
        // $client = Http::withToken($token); // Bearer token

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
            \Log::debug("C: {$method}: {$url}\n" . $this->debugBody($body, $headers));
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
