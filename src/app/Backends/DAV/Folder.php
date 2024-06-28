<?php

namespace App\Backends\DAV;

class Folder
{
    /** @var ?string Folder location (href property) */
    public $href;

    /** @var ?string Folder name (displayname property) */
    public $name;

    /** @var ?string Folder CTag (getctag property) */
    public $ctag;

    /** @var array Supported component set (supported-*-component-set property) */
    public $components = [];

    /** @var array Supported resource types (resourcetype property) */
    public $types = [];

    /** @var ?string Folder color (calendar-color property) */
    public $color;


    /**
     * Create Folder object from a DOMElement element
     *
     * @param \DOMElement $element DOM element with folder properties
     *
     * @return Folder
     */
    public static function fromDomElement(\DOMElement $element)
    {
        $folder = new Folder();

        if ($href = $element->getElementsByTagName('href')->item(0)) {
            $folder->href = $href->nodeValue;
        }

        if ($color = $element->getElementsByTagName('calendar-color')->item(0)) {
            if (preg_match('/^#[0-9a-fA-F]{6,8}$/', $color->nodeValue)) {
                $folder->color = substr($color->nodeValue, 1);
            }
        }

        if ($name = $element->getElementsByTagName('displayname')->item(0)) {
            $folder->name = $name->nodeValue;
        }

        if ($ctag = $element->getElementsByTagName('getctag')->item(0)) {
            $folder->ctag = $ctag->nodeValue;
        }

        $components = [];
        if ($set_element = $element->getElementsByTagName('supported-calendar-component-set')->item(0)) {
            foreach ($set_element->getElementsByTagName('comp') as $comp) {
                $components[] = $comp->attributes->getNamedItem('name')->nodeValue;
            }
        }

        $types = [];
        if ($type_element = $element->getElementsByTagName('resourcetype')->item(0)) {
            foreach ($type_element->childNodes as $node) {
                if ($node->nodeType == XML_ELEMENT_NODE) {
                    $_type = explode(':', $node->nodeName);
                    $types[] = count($_type) > 1 ? $_type[1] : $_type[0];
                }
            }
        }

        $folder->types = $types;
        $folder->components = $components;

        return $folder;
    }

    /**
     * Parse folder properties input into XML string to use in a request
     *
     * @return string
     */
    public function toXML($tag)
    {
        $ns = 'xmlns:d="DAV:"';
        $props = '';
        $type = null;

        if (in_array('addressbook', $this->types)) {
            $ns .= ' xmlns:c="urn:ietf:params:xml:ns:carddav"';
            $type = 'addressbook';
        } elseif (in_array('calendar', $this->types)) {
            $ns .= ' xmlns:c="urn:ietf:params:xml:ns:caldav"';
            $type = 'calendar';
        }

        $props .= '<d:resourcetype><d:collection/>' . ($type ? "<c:{$type}/>" : '') . '</d:resourcetype>';

        if (!empty($this->components)) {
            $props .= '<c:supported-calendar-component-set>';
            foreach ($this->components as $component) {
                $props .= '<c:comp name="' . $component . '"/>';
            }
            $props .= '</c:supported-calendar-component-set>';
        }

        if ($this->name !== null) {
            $props .= '<d:displayname>' . htmlspecialchars($this->name, ENT_XML1, 'UTF-8') . '</d:displayname>';
        }

        if ($this->color !== null) {
            $color = $this->color;
            if (strlen($color) && $color[0] != '#') {
                $color = '#' . $color;
            }

            $ns .= ' xmlns:a="http://apple.com/ns/ical/"';
            $props .= '<a:calendar-color>' . htmlspecialchars($color, ENT_XML1, 'UTF-8') . '</a:calendar-color>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            . "<d:{$tag} {$ns}><d:set><d:prop>{$props}</d:prop></d:set></d:{$tag}>";
    }

    /**
     * Get XML string for PROPFIND query on a folder
     *
     * @return string
     */
    public static function propfindXML()
    {
        $ns = implode(' ', [
            'xmlns:d="DAV:"',
            // 'xmlns:cs="http://calendarserver.org/ns/"',
            'xmlns:c="urn:ietf:params:xml:ns:caldav"',
            // 'xmlns:a="http://apple.com/ns/ical/"',
            // 'xmlns:k="Kolab:"'
        ]);

        // Note: <allprop> does not include some of the properties we're interested in
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind ' . $ns . '>'
                . '<d:prop>'
                    // . '<a:calendar-color/>'
                    . '<c:supported-calendar-component-set/>'
                    // . '<cs:getctag/>'
                    // . '<d:acl/>'
                    // . '<d:current-user-privilege-set/>'
                    . '<d:resourcetype/>'
                    . '<d:displayname/>'
                    // . '<k:alarms/>'
                . '</d:prop>'
            . '</d:propfind>';
    }
}
