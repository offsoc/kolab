<?php

namespace App\Backends\DAV;

use App\Backends\DAV;

class Search
{
    public $component;

    public $depth = 1;

    public $dataProperties = [];

    public $properties = [];

    public $withContent = false;


    public function __construct($component, $withContent = false)
    {
        $this->component = $component;
        $this->withContent = $withContent;
    }

    /**
     * Create string representation of the search
     *
     * @return string
     */
    public function __toString()
    {
        $ns = implode(' ', [
            'xmlns:d="DAV:"',
            'xmlns:c="' . DAV::NAMESPACES[$this->component] . '"',
        ]);

        // Return properties
        $props = [];
        foreach ($this->properties as $prop) {
            $props[] = '<' . $prop . '/>';
        }

        // Warning: Looks like some servers (iRony) ignore address-data/calendar-data
        // and return full VCARD/VCALENDAR. Which leads to unwanted loads of data in a response.
        if (!empty($this->dataProperties)) {
            $more_props = [];
            foreach ($this->dataProperties as $prop) {
                $more_props[] = '<c:prop name="' . $prop . '"/>';
            }

            if ($this->component == DAV::TYPE_VCARD) {
                $props[] = '<c:address-data>' . implode('', $more_props) . '</c:address-data>';
            } else {
                $props[] = '<c:calendar-data><c:comp name="VCALENDAR">'
                        . '<c:prop name="VERSION"/>'
                        . '<c:prop name="PRODID"/>'
                        . '<c:comp name="' . $this->component . '">' . implode('', $more_props) . '</c:comp>'
                    . '</c:comp></c:calendar-data>';
            }
        } elseif ($this->withContent) {
            if ($this->component == DAV::TYPE_VCARD) {
                $props[] = '<c:address-data/>';
            } else {
                $props[] = '<c:calendar-data/>';
            }
        }

        // Search filter
        $filter = '';
        if ($this->component == DAV::TYPE_VCARD) {
            $query = 'addressbook-query';
        } else {
            $query = 'calendar-query';
            $filter = '<c:filter>'
                . '<c:comp-filter name="VCALENDAR">'
                . '<c:comp-filter name="' . $this->component . '" /></c:comp-filter>'
                . '</c:filter>';
        }

        if (empty($props)) {
            $props = '<d:allprop/>';
        } else {
            $props = '<d:prop>' . implode('', $props) . '</d:prop>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            . "<c:{$query} {$ns}>" . $props . $filter . "</c:{$query}>";
    }
}
